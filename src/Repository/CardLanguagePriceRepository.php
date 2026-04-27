<?php

namespace App\Repository;

use App\Entity\Card;
use App\Entity\CardLanguagePrice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CardLanguagePriceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardLanguagePrice::class);
    }

    /**
     * @return list<CardLanguagePrice>
     */
    public function findForCard(Card $card): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.card = :card')
            ->setParameter('card', $card)
            ->orderBy('p.languageLabel', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
