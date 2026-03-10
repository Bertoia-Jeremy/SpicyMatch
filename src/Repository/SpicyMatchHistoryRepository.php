<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpicyMatchHistory>
 *
 * @method SpicyMatchHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method SpicyMatchHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method SpicyMatchHistory[]    findAll()
 * @method SpicyMatchHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
        return $this->createQueryBuilder('smh')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('smh.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return SpicyMatchHistory[]
     */
    public function findFavoritesByUser(Users $user): array
    {
        return $this->createQueryBuilder('smh')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('smh.favorite = true')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('smh.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
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
