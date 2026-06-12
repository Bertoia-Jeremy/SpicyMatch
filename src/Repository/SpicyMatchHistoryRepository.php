<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpicyMatchHistory>
 */
class SpicyMatchHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpicyMatchHistory::class);
    }

    /**
     * @return SpicyMatchHistory[]
     */
    public function findByUser(Users $user): array
    {
        return $this->findByUserQuery($user)
            ->getResult();
    }

    /**
     * Fetch at most $limit histories for $user — avoids loading the full collection
     * when only a preview is needed (e.g., profile page).
     *
     * @return SpicyMatchHistory[]
     */
    public function findByUserWithLimit(Users $user, int $limit): array
    {
        return $this->createQueryBuilder('smh')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('smh.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return \Doctrine\ORM\Query<null, mixed>
     */
    public function findByUserQuery(Users $user): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('smh')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('smh.createdAt', 'DESC')
            ->getQuery();
    }

    /**
     * @return SpicyMatchHistory[]
     */
    public function findFavoritesByUser(Users $user): array
    {
        return $this->findFavoritesByUserQuery($user)
            ->getResult();
    }

    /**
     * @return \Doctrine\ORM\Query<null, mixed>
     */
    public function findFavoritesByUserQuery(Users $user): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('smh')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('smh.favorite = true')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('smh.createdAt', 'DESC')
            ->getQuery();
    }

    /**
     * @return \Doctrine\ORM\Query<null, mixed>
     */
    public function findManualByUserQuery(Users $user): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('smh')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('sm.isManual = true')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('smh.createdAt', 'DESC')
            ->getQuery();
    }

    public function countFavoritesByUser(Users $user): int
    {
        return (int) $this->createQueryBuilder('smh')
            ->select('COUNT(smh.id)')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('smh.favorite = true')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByUser(Users $user): int
    {
        return (int) $this->createQueryBuilder('smh')
            ->select('COUNT(smh.id)')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDistinctSpicesByUser(Users $user): int
    {
        return (int) $this->createQueryBuilder('smh')
            ->select('COUNT(DISTINCT s.id)')
            ->join('smh.spicyMatch', 'sm')
            ->join('sm.spices', 's')
            ->where('sm.user = :user')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
