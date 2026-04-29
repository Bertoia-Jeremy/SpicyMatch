<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserAchievement;
use App\Entity\UserProgression;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAchievement>
 */
class UserAchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAchievement::class);
    }

    /**
     * Charge les UserAchievement d'une progression avec leurs Achievement en une seule requête (évite le N+1).
     *
     * @return UserAchievement[]
     */
    public function findByProgressionWithAchievement(UserProgression $progression): array
    {
        return $this->createQueryBuilder('ua')
            ->join('ua.achievement', 'a')
            ->addSelect('a')
            ->where('ua.userProgression = :progression')
            ->setParameter('progression', $progression)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
