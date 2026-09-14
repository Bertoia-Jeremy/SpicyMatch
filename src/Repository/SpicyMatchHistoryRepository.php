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

    /**
     * @return array<int, int>
     */
    public function countGroupedByUser(): array
    {
        return $this->mapGroupedByUser(
            $this->createQueryBuilder('smh')
                ->select('IDENTITY(sm.user) AS uid', 'COUNT(smh.id) AS total')
                ->join('smh.spicyMatch', 'sm')
                ->where('smh.deletedAt IS NULL')
                ->andWhere('sm.user IS NOT NULL')
                ->groupBy('sm.user')
                ->getQuery()
                ->getArrayResult()
        );
    }

    /**
     * @return array<int, int>
     */
    public function countDistinctSpicesGroupedByUser(): array
    {
        return $this->mapGroupedByUser(
            $this->createQueryBuilder('smh')
                ->select('IDENTITY(sm.user) AS uid', 'COUNT(DISTINCT s.id) AS total')
                ->join('smh.spicyMatch', 'sm')
                ->join('sm.spices', 's')
                ->where('smh.deletedAt IS NULL')
                ->andWhere('sm.user IS NOT NULL')
                ->groupBy('sm.user')
                ->getQuery()
                ->getArrayResult()
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, int>
     */
    private function mapGroupedByUser(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['uid']] = (int) $row['total'];
        }

        return $result;
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
