<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

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
            ->orderBy('smh.createdAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Query<null, mixed>
     */
    public function findByUserQuery(Users $user): Query
    {
        return $this->createQueryBuilder('smh')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('smh.createdAt', SortDirection::Descending)
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
     * @return Query<null, mixed>
     */
    public function findFavoritesByUserQuery(Users $user): Query
    {
        return $this->createQueryBuilder('smh')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('smh.favorite = true')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('smh.createdAt', SortDirection::Descending)
            ->getQuery();
    }

    /**
     * @return Query<null, mixed>
     */
    public function findManualByUserQuery(Users $user): Query
    {
        return $this->createQueryBuilder('smh')
            ->join('smh.spicyMatch', 'sm')
            ->where('sm.user = :user')
            ->andWhere('sm.isManual = true')
            ->andWhere('smh.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('smh.createdAt', SortDirection::Descending)
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
            ->andWhere('smh.sealedAt IS NOT NULL')
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
                ->andWhere('smh.sealedAt IS NOT NULL')
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
                ->andWhere('smh.sealedAt IS NOT NULL')
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

    public function preloadForRecipe(SpicyMatchHistory $history, string $locale): void
    {
        $translated = $locale !== 'fr';

        $tips = $this->createQueryBuilder('smh')
            ->addSelect('pt', 'pts', 'ag', 'ct')
            ->leftJoin('smh.preparationTips', 'pt')
            ->leftJoin('pt.spice', 'pts')
            ->leftJoin('pts.aromaticGroups', 'ag')
            ->leftJoin('smh.cookingTips', 'ct')
            ->where('smh = :history')
            ->setParameter('history', $history);
        if ($translated) {
            $tips->leftJoin('pt.translations', 'ptt', 'WITH', 'ptt.locale = :locale')
                ->leftJoin('pts.translations', 'st', 'WITH', 'st.locale = :locale')
                ->leftJoin('ag.translations', 'agt', 'WITH', 'agt.locale = :locale')
                ->leftJoin('ct.translations', 'ctt', 'WITH', 'ctt.locale = :locale')
                ->addSelect('ptt', 'st', 'agt', 'ctt')
                ->setParameter('locale', $locale);
        }
        $tips->getQuery()
            ->getResult();

        $mortar = $this->createQueryBuilder('smh')
            ->addSelect('sm', 's', 'sg', 'ac')
            ->join('smh.spicyMatch', 'sm')
            ->leftJoin('sm.spices', 's')
            ->leftJoin('s.aromaticGroups', 'sg')
            ->leftJoin('s.aromaticsCompounds', 'ac')
            ->where('smh = :history')
            ->setParameter('history', $history);
        if ($translated) {
            $mortar->leftJoin('ac.translations', 'act', 'WITH', 'act.locale = :locale')
                ->addSelect('act')
                ->setParameter('locale', $locale);
        }
        $mortar->getQuery()
            ->getResult();
    }

    public function countDistinctSpicesByUser(Users $user): int
    {
        return (int) $this->createQueryBuilder('smh')
            ->select('COUNT(DISTINCT s.id)')
            ->join('smh.spicyMatch', 'sm')
            ->join('sm.spices', 's')
            ->where('sm.user = :user')
            ->andWhere('smh.deletedAt IS NULL')
            ->andWhere('smh.sealedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
