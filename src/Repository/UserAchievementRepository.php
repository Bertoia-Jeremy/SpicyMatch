<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserAchievement;
use App\Entity\UserProgression;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

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
     * @return array<int, int>
     */
    public function sumXpRewardGroupedByProgression(): array
    {
        $rows = $this->createQueryBuilder('ua')
            ->select('IDENTITY(ua.userProgression) AS pid', 'SUM(a.xpReward) AS total')
            ->join('ua.achievement', 'a')
            ->groupBy('ua.userProgression')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['pid']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * @return UserAchievement[]
     */
    public function findByProgressionWithAchievement(UserProgression $progression): array
    {
        return $this->createQueryBuilder('ua')
            ->join('ua.achievement', 'a')
            ->addSelect('a')
            ->where('ua.userProgression = :progression')
            ->setParameter('progression', $progression)
            ->orderBy('ua.unlockedAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }
}
