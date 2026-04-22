<?php

namespace App\Repository;

use App\Entity\Card;
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

    public function findSuggestions(string $query, int $limit = 30): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('DISTINCT c.name, c.cardNumber')
            ->andWhere('LOWER(c.name) LIKE :query OR LOWER(c.nameNumbered) LIKE :query OR LOWER(c.cardNumber) LIKE :query OR LOWER(c.cardNumber) LIKE :cardQuery')
            ->setParameter('query', '%' . mb_strtolower(trim($query)) . '%')
            ->setParameter('cardQuery', '%' . $this->normalizedCardSearchValue($query) . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit);

        $suggestions = [];

        foreach ($queryBuilder->getQuery()->getScalarResult() as $row) {
            $name = (string) ($row['name'] ?? '');
            $cardNumber = trim((string) ($row['cardNumber'] ?? ''));

            if ($cardNumber !== '') {
                $suggestions[$name . ' ' . $cardNumber] = $name . ' ' . $cardNumber;
                continue;
            }

            if ($name !== '') {
                $suggestions[$name] = $name;
            }
        }

        return array_values($suggestions);
    }

    private function applySearch($queryBuilder, ?string $query): void
    {
        $query = trim((string) $query);

        if ($query === '') {
            return;
        }

        $normalizedQuery = mb_strtolower($query);
        $normalizedCardQuery = $this->normalizedCardSearchValue($query);

        $queryBuilder
            ->andWhere('LOWER(c.name) LIKE :query OR LOWER(c.nameNumbered) LIKE :query OR LOWER(c.cardNumber) LIKE :query OR LOWER(c.cardNumber) LIKE :cardQuery')
            ->setParameter('query', '%' . $normalizedQuery . '%')
            ->setParameter('cardQuery', '%' . $normalizedCardQuery . '%');
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
