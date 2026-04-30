<?php

namespace App\Repository;

use App\Entity\Card;
use App\Entity\CardComment;
use App\Entity\DeckCard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    public function searchPage(?string $query, int $page, int $perPage, string $sort, ?string $rarity = null, ?string $collection = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->leftJoin('c.artist', 'a')
            ->addSelect('a');

        $this->applySearch($queryBuilder, $query);
        $this->applyRarity($queryBuilder, $rarity);
        $this->applyCollection($queryBuilder, $collection);
        $this->applySort($queryBuilder, $sort);

        return $queryBuilder
            ->setFirstResult((max(1, $page) - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function searchAll(?string $query, string $sort, ?string $rarity = null, ?string $collection = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->leftJoin('c.artist', 'a')
            ->addSelect('a');

        $this->applySearch($queryBuilder, $query);
        $this->applyRarity($queryBuilder, $rarity);
        $this->applyCollection($queryBuilder, $collection);
        $this->applySort($queryBuilder, $sort);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function countSearch(?string $query, ?string $rarity = null, ?string $collection = null): int
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        $this->applySearch($queryBuilder, $query);
        $this->applyRarity($queryBuilder, $rarity);
        $this->applyCollection($queryBuilder, $collection);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function findRarityOptions(?string $query = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('DISTINCT c.rarity AS rarity')
            ->andWhere('c.rarity IS NOT NULL')
            ->andWhere("c.rarity <> ''")
            ->orderBy('c.rarity', 'ASC');

        $this->applySearch($queryBuilder, $query);

        return array_column($queryBuilder->getQuery()->getScalarResult(), 'rarity');
    }

    public function findCollectionOptions(?string $query = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->select('DISTINCT e.code AS code, e.name AS name')
            ->andWhere('e.id IS NOT NULL')
            ->andWhere("(e.code IS NOT NULL AND e.code <> '') OR (e.name IS NOT NULL AND e.name <> '')")
            ->orderBy('e.code', 'ASC')
            ->addOrderBy('e.name', 'ASC');

        $this->applySearch($queryBuilder, $query);

        $options = [];

        foreach ($queryBuilder->getQuery()->getScalarResult() as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $value = $code !== '' ? $code : $name;

            if ($value === '' || isset($options[$value])) {
                continue;
            }

            $options[$value] = [
                'value' => $value,
                'label' => $code !== '' && $name !== '' ? sprintf('%s - %s', $code, $name) : $value,
            ];
        }

        return array_values($options);
    }

    public function findApiIdWithRelations(int $apiId): ?Card
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->leftJoin('c.artist', 'a')
            ->addSelect('a')
            ->andWhere('c.apiId = :apiId')
            ->setParameter('apiId', $apiId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatest(int $limit = 8): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->leftJoin('c.artist', 'a')
            ->addSelect('a')
            ->orderBy('c.updatedAt', 'DESC')
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findMostValuable(int $limit = 16): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->leftJoin('c.artist', 'a')
            ->addSelect('a')
            ->andWhere('c.averageNearMintPriceCents IS NOT NULL')
            ->andWhere('c.averageNearMintPriceCents > 0')
            ->orderBy('c.averageNearMintPriceCents', 'DESC')
            ->addOrderBy('c.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Cards returned here are the local cache entries that power site features:
     * deck cards, commented cards, and cards already shown with cached prices.
     * This keeps scheduled price refreshes focused on community usage instead
     * of redistributing the full CardTrader catalog.
     *
     * @return list<Card>
     */
    public function findTrackedForPriceRefresh(int $limit = 250): array
    {
        return $this->createQueryBuilder('c')
            ->select('DISTINCT c')
            ->leftJoin(DeckCard::class, 'dc', 'WITH', 'dc.card = c')
            ->leftJoin(CardComment::class, 'cc', 'WITH', 'cc.card = c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->andWhere('dc.id IS NOT NULL OR cc.id IS NOT NULL OR c.averageNearMintPriceCents IS NOT NULL')
            ->orderBy('c.priceUpdatedAt', 'ASC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->setMaxResults(max(1, min(1000, $limit)))
            ->getQuery()
            ->getResult();
    }

    public function findSuggestions(string $query, int $limit = 30): array
    {
        $exactCardCode = $this->exactCardSearchValue($query);

        $queryBuilder = $this->createQueryBuilder('c')
            ->select('c.apiId AS id, c.name AS name, c.nameNumbered AS nameNumbered, c.cardNumber AS cardNumber')
            ->andWhere('LOWER(c.name) LIKE :query OR LOWER(c.nameNumbered) LIKE :query OR LOWER(c.cardNumber) LIKE :query OR LOWER(c.cardNumber) LIKE :cardQuery OR LOWER(c.cardNumber) = :exactCardCode')
            ->setParameter('query', '%' . mb_strtolower(trim($query)) . '%')
            ->setParameter('cardQuery', '%' . $this->normalizedCardSearchValue($query) . '%')
            ->setParameter('exactCardCode', $exactCardCode)
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit);

        $suggestions = [];

        foreach ($queryBuilder->getQuery()->getScalarResult() as $row) {
            $name = (string) ($row['name'] ?? '');
            $nameNumbered = trim((string) ($row['nameNumbered'] ?? ''));
            $cardNumber = trim((string) ($row['cardNumber'] ?? ''));
            $label = $cardNumber !== '' ? $name . ' ' . $cardNumber : $name;

            if ($label === '') {
                continue;
            }

            $suggestions[$label] = [
                'id' => (int) $row['id'],
                'label' => $label,
                'name' => $nameNumbered !== '' ? $nameNumbered : $name,
            ];
        }

        return array_values($suggestions);
    }

    public function findBestMatchForDeckLine(string $query): ?Card
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $exactCardCode = $this->exactCardSearchValue($query);

        if ($exactCardCode !== '__no_match__') {
            $card = $this->createQueryBuilder('c')
                ->leftJoin('c.episode', 'e')
                ->addSelect('e')
                ->andWhere('LOWER(c.cardNumber) = :cardNumber')
                ->setParameter('cardNumber', $exactCardCode)
                ->orderBy('c.name', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($card instanceof Card) {
                return $card;
            }
        }

        $normalizedQuery = mb_strtolower($query);
        $normalizedCardQuery = $this->normalizedCardSearchValue($query);

        return $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->andWhere('LOWER(c.name) = :exactName OR LOWER(c.nameNumbered) = :exactName OR LOWER(c.name) LIKE :query OR LOWER(c.nameNumbered) LIKE :query OR LOWER(c.cardNumber) LIKE :cardQuery')
            ->setParameter('exactName', $normalizedQuery)
            ->setParameter('query', '%' . $normalizedQuery . '%')
            ->setParameter('cardQuery', '%' . $normalizedCardQuery . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Card>
     */
    public function findDeckBuilderMatches(string $query, int $limit = 12, ?string $collection = null): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $exactCardCode = $this->exactCardSearchValue($query);
        $normalizedQuery = mb_strtolower($query);
        $normalizedCardQuery = $this->normalizedCardSearchValue($query);

        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->andWhere('LOWER(c.name) LIKE :query OR LOWER(c.nameNumbered) LIKE :query OR LOWER(c.cardNumber) LIKE :query OR LOWER(c.cardNumber) LIKE :cardQuery OR LOWER(c.cardNumber) = :exactCardCode')
            ->setParameter('query', '%' . $normalizedQuery . '%')
            ->setParameter('cardQuery', '%' . $normalizedCardQuery . '%')
            ->setParameter('exactCardCode', $exactCardCode);

        $this->applyCollection($queryBuilder, $collection);

        return $queryBuilder
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('c.cardNumber', 'ASC')
            ->setMaxResults(max(1, min(30, $limit)))
            ->getQuery()
            ->getResult();
    }

    private function applySearch($queryBuilder, ?string $query): void
    {
        $query = trim((string) $query);

        if ($query === '') {
            return;
        }

        $normalizedQuery = mb_strtolower($query);
        $normalizedCardQuery = $this->normalizedCardSearchValue($query);
        $exactCardCode = $this->exactCardSearchValue($query);

        $queryBuilder
            ->andWhere('LOWER(c.name) LIKE :query OR LOWER(c.nameNumbered) LIKE :query OR LOWER(c.cardNumber) LIKE :query OR LOWER(c.cardNumber) LIKE :cardQuery OR LOWER(c.cardNumber) = :exactCardCode')
            ->setParameter('query', '%' . $normalizedQuery . '%')
            ->setParameter('cardQuery', '%' . $normalizedCardQuery . '%')
            ->setParameter('exactCardCode', $exactCardCode);
    }

    private function normalizeCardQuery(string $query): string
    {
        $query = mb_strtolower(trim($query));
        $query = preg_replace('/[[:space:]]+/', '', $query) ?? $query;
        $query = preg_replace('/[^a-z0-9-]/', '', $query) ?? $query;

        return trim($query, '-');
    }

    private function normalizedCardSearchValue(string $query): string
    {
        $normalized = $this->normalizeCardQuery($query);

        return $normalized !== '' ? $normalized : '__no_match__';
    }

    private function exactCardSearchValue(string $query): string
    {
        $normalized = mb_strtolower(trim($query));

        if (preg_match('/\b[a-z]{1,4}\d{0,2}-\d{3}\b/', $normalized, $matches) !== 1) {
            return '__no_match__';
        }

        return $matches[0];
    }

    private function applyRarity($queryBuilder, ?string $rarity): void
    {
        $rarity = trim((string) $rarity);

        if ($rarity === '') {
            return;
        }

        $queryBuilder
            ->andWhere('c.rarity = :rarity')
            ->setParameter('rarity', $rarity);
    }

    private function applyCollection($queryBuilder, ?string $collection): void
    {
        $collection = trim((string) $collection);

        if ($collection === '') {
            return;
        }

        $queryBuilder
            ->leftJoin('c.episode', 'filter_episode')
            ->andWhere('filter_episode.code = :collection OR filter_episode.name = :collection')
            ->setParameter('collection', $collection);
    }

    private function applySort($queryBuilder, string $sort): void
    {
        match ($sort) {
            'recent' => $queryBuilder->orderBy('c.updatedAt', 'DESC')->addOrderBy('c.name', 'ASC'),
            'name_desc' => $queryBuilder->orderBy('c.name', 'DESC'),
            default => $queryBuilder->orderBy('c.name', 'ASC'),
        };
    }
}
