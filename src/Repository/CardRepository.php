<?php

namespace App\Repository;

use App\Entity\Card;
use App\Entity\CardPrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    public function searchPage(?string $query, int $page, int $perPage, string $sort): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->leftJoin('c.artist', 'a')
            ->addSelect('a')
            ->leftJoin(CardPrice::class, 'p', 'WITH', 'p.card = c');

        $this->applySearch($queryBuilder, $query);
        $this->applySort($queryBuilder, $sort);

        return $queryBuilder
            ->setFirstResult((max(1, $page) - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    public function countSearch(?string $query): int
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        $this->applySearch($queryBuilder, $query);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function findApiIdWithRelations(int $apiId): ?Card
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->leftJoin('c.artist', 'a')
            ->addSelect('a')
            ->leftJoin(CardPrice::class, 'p', 'WITH', 'p.card = c')
            ->andWhere('c.apiId = :apiId')
            ->setParameter('apiId', $apiId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
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

    private function applySort($queryBuilder, string $sort): void
    {
        match ($sort) {
            'price_highest' => $queryBuilder->orderBy('p.lowestNearMint', 'DESC')->addOrderBy('c.name', 'ASC'),
            'price_lowest' => $queryBuilder->orderBy('p.lowestNearMint', 'ASC')->addOrderBy('c.name', 'ASC'),
            default => $queryBuilder->orderBy('c.name', 'ASC'),
        };
    }
}
