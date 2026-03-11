<?php

declare(strict_types=1);

namespace App\Repository;

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
}
