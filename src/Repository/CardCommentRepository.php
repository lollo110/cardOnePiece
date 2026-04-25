<?php

namespace App\Repository;

use App\Entity\CardComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CardCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardComment::class);
    }

    public function findForCard(\App\Entity\Card $card, int $limit = 100): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.card = :card')
            ->andWhere('c.moderationStatus = :status')
            ->setParameter('card', $card)
            ->setParameter('status', CardComment::STATUS_PUBLISHED)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findForCardDiscussion(\App\Entity\Card $card, string $discussionType, string $language, int $limit = 100): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.card = :card')
            ->andWhere('c.discussionType = :discussionType')
            ->andWhere('c.language = :language')
            ->andWhere('c.moderationStatus = :status')
            ->setParameter('card', $card)
            ->setParameter('discussionType', $discussionType)
            ->setParameter('language', $language)
            ->setParameter('status', CardComment::STATUS_PUBLISHED)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.parentComment', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOnePublishedForDiscussion(\App\Entity\Card $card, int $id, string $discussionType, string $language): ?CardComment
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.card = :card')
            ->andWhere('c.discussionType = :discussionType')
            ->andWhere('c.language = :language')
            ->andWhere('c.moderationStatus = :status')
            ->setParameter('id', $id)
            ->setParameter('card', $card)
            ->setParameter('discussionType', $discussionType)
            ->setParameter('language', $language)
            ->setParameter('status', CardComment::STATUS_PUBLISHED)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
