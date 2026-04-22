<?php

namespace App\Repository;

use App\Entity\BlogComment;
use App\Entity\BlogThread;
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

    public function findForThread(BlogThread $thread, int $limit = 200): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.thread = :thread')
            ->andWhere('c.moderationStatus = :status')
            ->setParameter('thread', $thread)
            ->setParameter('status', BlogComment::STATUS_PUBLISHED)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.parentComment', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOnePublishedForThread(int $id, BlogThread $thread): ?BlogComment
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.thread = :thread')
            ->andWhere('c.moderationStatus = :status')
            ->setParameter('id', $id)
            ->setParameter('thread', $thread)
            ->setParameter('status', BlogComment::STATUS_PUBLISHED)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
