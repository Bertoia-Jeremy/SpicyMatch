<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Achievement;
use App\Enum\AchievementTrigger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Achievement>
 */
class AchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Achievement::class);
    }

    /**
     * @return Achievement[]
     */
    public function findByTrigger(AchievementTrigger $trigger): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.trigger = :trigger')
            ->setParameter('trigger', $trigger)
            ->getQuery()
            ->getResult()
        ;
    }
}
