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

    /**
     * Records a spice view for today.
     * Returns true if this is a new view (not seen today), false if already recorded.
     */
    public function recordView(Users $user, Spices $spice): bool
    {
        $today = new \DateTimeImmutable('today');

        $existing = $this->findOneBy([
            'user' => $user,
            'spice' => $spice,
            'viewedDay' => $today,
        ]);

        if (null !== $existing) {
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

    /**
     * Total number of views recorded for this user (one row = one distinct day/spice pair).
     */
    public function countByUser(Users $user): int
    {
        return (int) $this->createQueryBuilder('sv')
            ->select('COUNT(sv.id)')
            ->where('sv.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count of distinct spices the user has viewed that belong to the given aromatic group.
     * Drives the GROUP_MASTERY_READ trigger.
     */
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
     * Count of distinct preparation methods the user has encountered across viewed spices.
     * Drives the ALL_PREPARATION_METHODS_READ trigger.
     */
    public function countDistinctPreparationMethodsSeenBy(Users $user): int
    {
        return (int) $this->createQueryBuilder('sv')
            ->select('COUNT(DISTINCT pm.id)')
            ->innerJoin('sv.spice', 's')
            ->innerJoin('s.preparationTips', 'pt')
            ->innerJoin('pt.method', 'pm')
            ->where('sv.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
