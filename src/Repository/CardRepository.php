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

    public function searchPage(?string $query, int $page, int $perPage, string $sort, ?string $rarity = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->leftJoin('c.artist', 'a')
            ->addSelect('a');

        $this->applySearch($queryBuilder, $query);
        $this->applyRarity($queryBuilder, $rarity);
        $this->applySort($queryBuilder, $sort);

        return $queryBuilder
            ->setFirstResult((max(1, $page) - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function searchAll(?string $query, string $sort, ?string $rarity = null): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->leftJoin('c.artist', 'a')
            ->addSelect('a');

        $this->applySearch($queryBuilder, $query);
        $this->applyRarity($queryBuilder, $rarity);
        $this->applySort($queryBuilder, $sort);

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    public function countSearch(?string $query, ?string $rarity = null): int
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        $this->applySearch($queryBuilder, $query);
        $this->applyRarity($queryBuilder, $rarity);

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

    public function findSuggestions(string $query, int $limit = 8): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('DISTINCT c.name')
            ->andWhere('LOWER(c.name) LIKE :query OR LOWER(c.nameNumbered) LIKE :query')
            ->setParameter('query', '%' . mb_strtolower($query) . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults($limit);

        return array_column($queryBuilder->getQuery()->getScalarResult(), 'name');
    }

    private function applySearch($queryBuilder, ?string $query): void
    {
        $query = trim((string) $query);

        if ($query === '') {
            return;
        }

        $queryBuilder
            ->andWhere('LOWER(c.name) LIKE :query OR LOWER(c.nameNumbered) LIKE :query OR LOWER(c.cardNumber) LIKE :query')
            ->setParameter('query', '%' . mb_strtolower($query) . '%');
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

    private function applySort($queryBuilder, string $sort): void
    {
        match ($sort) {
            'recent' => $queryBuilder->orderBy('c.updatedAt', 'DESC')->addOrderBy('c.name', 'ASC'),
            'name_desc' => $queryBuilder->orderBy('c.name', 'DESC'),
            default => $queryBuilder->orderBy('c.name', 'ASC'),
        };
    }
}
