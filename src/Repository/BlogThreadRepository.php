<?php

namespace App\Repository;

use App\Entity\BlogComment;
use App\Entity\BlogThread;
use App\Entity\BlogTopic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogThread::class);
    }

    public function findForRoom(BlogTopic $topic, int $limit = 100): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.topic = :topic')
            ->andWhere('t.moderationStatus = :status')
            ->setParameter('topic', $topic)
            ->setParameter('status', BlogThread::STATUS_PUBLISHED)
            ->orderBy('t.lastActivityAt', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOnePublishedForRoom(int $id, BlogTopic $topic): ?BlogThread
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.id = :id')
            ->andWhere('t.topic = :topic')
            ->andWhere('t.moderationStatus = :status')
            ->setParameter('id', $id)
            ->setParameter('topic', $topic)
            ->setParameter('status', BlogThread::STATUS_PUBLISHED)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findTopCommentedForTopicSlug(string $slug): ?array
    {
        $result = $this->createQueryBuilder('t')
            ->select('t AS thread')
            ->addSelect('COUNT(c.id) AS commentCount')
            ->innerJoin('t.topic', 'topic')
            ->innerJoin(BlogComment::class, 'c', 'WITH', 'c.thread = t AND c.moderationStatus = :commentStatus')
            ->andWhere('topic.slug = :slug')
            ->andWhere('t.moderationStatus = :threadStatus')
            ->setParameter('slug', $slug)
            ->setParameter('threadStatus', BlogThread::STATUS_PUBLISHED)
            ->setParameter('commentStatus', BlogComment::STATUS_PUBLISHED)
            ->groupBy('t.id')
            ->orderBy('commentCount', 'DESC')
            ->addOrderBy('t.lastActivityAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$result) {
            return null;
        }

        $result['commentCount'] = (int) $result['commentCount'];

        return $result;
    }
}
