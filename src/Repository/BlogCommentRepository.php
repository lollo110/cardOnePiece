<?php

namespace App\Repository;

use App\Entity\BlogComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogComment::class);
    }

    public function findForTopic(\App\Entity\BlogTopic $topic, int $limit = 100): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.topic = :topic')
            ->andWhere('c.moderationStatus = :status')
            ->setParameter('topic', $topic)
            ->setParameter('status', BlogComment::STATUS_PUBLISHED)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
