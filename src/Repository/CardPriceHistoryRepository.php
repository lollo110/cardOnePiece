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

    public function findOneForDay(Card $card, string $languageKey, \DateTimeImmutable $recordedOn): ?CardPriceHistory
    {
        return $this->findOneBy([
            'card' => $card,
            'languageKey' => $languageKey,
            'recordedOn' => $recordedOn,
        ]);
    }

    /**
     * @return list<CardPriceHistory>
     */
    public function findForCardRange(Card $card, ?\DateTimeImmutable $since = null): array
    {
        $queryBuilder = $this->createQueryBuilder('h')
            ->andWhere('h.card = :card')
            ->setParameter('card', $card)
            ->orderBy('h.languageLabel', 'ASC')
            ->addOrderBy('h.recordedOn', 'ASC');

        if ($since !== null) {
            $queryBuilder
                ->andWhere('h.recordedOn >= :since')
                ->setParameter('since', $since);
        }

        return $queryBuilder
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<CardPriceHistory>
     */
    public function findRecentForCard(Card $card, int $limitPerLanguage = 90): array
    {
        $rows = $this->createQueryBuilder('h')
            ->andWhere('h.card = :card')
            ->setParameter('card', $card)
            ->orderBy('h.languageLabel', 'ASC')
            ->addOrderBy('h.recordedOn', 'DESC')
            ->getQuery()
            ->getResult();

        $counts = [];
        $history = [];

        foreach ($rows as $row) {
            $languageKey = $row->getLanguageKey();
            $counts[$languageKey] ??= 0;

            if ($counts[$languageKey] >= $limitPerLanguage) {
                continue;
            }

            $history[] = $row;
            $counts[$languageKey]++;
        }

        usort($history, static function (CardPriceHistory $a, CardPriceHistory $b): int {
            return [$a->getLanguageLabel(), $a->getRecordedOn()->format('Y-m-d')] <=> [$b->getLanguageLabel(), $b->getRecordedOn()->format('Y-m-d')];
        });

        return $history;
    }
}
