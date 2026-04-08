<?php

namespace App\Repository;

use App\Entity\Card;
use App\Entity\CardPriceHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CardPriceHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardPriceHistory::class);
    }

    public function findForCard(Card $card, int $limit = 30): array
    {
        $rows = $this->createQueryBuilder('h')
            ->andWhere('h.card = :card')
            ->setParameter('card', $card)
            ->orderBy('h.capturedOn', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($rows);
    }
}
