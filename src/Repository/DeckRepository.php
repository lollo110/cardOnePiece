<?php

namespace App\Repository;

use App\Entity\Deck;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DeckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deck::class);
    }

    /**
     * @return list<Deck>
     */
    public function findPublicDecks(int $limit = 40): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.owner', 'o')
            ->addSelect('o')
            ->andWhere('d.isPublic = true')
            ->orderBy('d.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Deck>
     */
    public function findForOwner(User $owner): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('d.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findVisible(int $id, ?User $user): ?Deck
    {
        $queryBuilder = $this->createQueryBuilder('d')
            ->leftJoin('d.owner', 'o')
            ->addSelect('o')
            ->leftJoin('d.cards', 'deck_cards')
            ->addSelect('deck_cards')
            ->leftJoin('deck_cards.card', 'c')
            ->addSelect('c')
            ->leftJoin('c.episode', 'e')
            ->addSelect('e')
            ->andWhere('d.id = :id')
            ->setParameter('id', $id);

        if ($user === null) {
            $queryBuilder->andWhere('d.isPublic = true');
        } elseif (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $queryBuilder
                ->andWhere('d.isPublic = true OR d.owner = :user')
                ->setParameter('user', $user);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }
}
