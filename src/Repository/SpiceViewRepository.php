<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Entity\SpiceView;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpiceView>
 */
class SpiceViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpiceView::class);
    }

    public function recordView(Users $user, Spices $spice): bool
    {
        $today = new \DateTimeImmutable('today');

        $existing = $this->findOneBy([
            'user' => $user,
            'spice' => $spice,
            'viewedDay' => $today,
        ]);

        if ($existing !== null) {
            return false;
        }

        $view = new SpiceView($user, $spice);
        $this->getEntityManager()
            ->persist($view);
        $this->getEntityManager()
            ->flush();

        return true;
    }

    public function countDistinctSpicesByUser(Users $user): int
    {
        return (int) $this->createQueryBuilder('sv')
            ->select('COUNT(DISTINCT sv.spice)')
            ->where('sv.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByUser(Users $user): int
    {
        return (int) $this->createQueryBuilder('sv')
            ->select('COUNT(sv.id)')
            ->where('sv.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByGroup(Users $user, AromaticGroups $group): int
    {
        return (int) $this->createQueryBuilder('sv')
            ->select('COUNT(DISTINCT sv.spice)')
            ->innerJoin('sv.spice', 's')
            ->innerJoin('s.aromaticGroups', 'ag')
            ->where('sv.user = :user')
            ->andWhere('ag = :group')
            ->setParameter('user', $user)
            ->setParameter('group', $group)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, int>
     */
    public function countGroupedByUser(): array
    {
        return $this->mapGroupedByUser(
            $this->createQueryBuilder('sv')
                ->select('IDENTITY(sv.user) AS uid', 'COUNT(sv.id) AS total')
                ->groupBy('sv.user')
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
            $this->createQueryBuilder('sv')
                ->select('IDENTITY(sv.user) AS uid', 'COUNT(DISTINCT sv.spice) AS total')
                ->groupBy('sv.user')
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

    public function countDistinctPreparationMethodsSeenBy(Users $user): int
    {
        return (int) $this->createQueryBuilder('sv')
            ->select('COUNT(DISTINCT pm.id)')
            ->innerJoin('sv.spice', 's')
            ->innerJoin('s.preparationTips', 'pt')
            ->innerJoin('pt.preparationMethod', 'pm')
            ->where('sv.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
