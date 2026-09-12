<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\AchievementProgress;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AchievementProgress>
 */
class AchievementProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AchievementProgress::class);
    }

    public function findOrCreateForUser(Users $user, Achievement $achievement): AchievementProgress
    {
        $ap = $this->findOneBy([
            'user' => $user,
            'achievement' => $achievement,
        ]);
        if ($ap === null) {
            $ap = new AchievementProgress();
            $ap->setUser($user)
                ->setAchievement($achievement);
            $this->getEntityManager()
                ->persist($ap);
        }

        return $ap;
    }

    /**
     * Batch variant: load all existing AchievementProgress rows for the given (user, achievements)
     * pairs in a single query, persist missing ones, return them indexed by achievement id.
     *
     * @param Achievement[] $achievements
     *
     * @return array<int, AchievementProgress>
     */
    public function findOrCreateBatchForUser(Users $user, array $achievements): array
    {
        if ($achievements === []) {
            return [];
        }

        $ids = array_values(array_filter(array_map(fn (Achievement $a) => $a->getId(), $achievements)));

        $existing = $ids !== []
            ? $this->createQueryBuilder('ap')
                ->where('ap.user = :user')
                ->andWhere('ap.achievement IN (:ids)')
                ->setParameter('user', $user)
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getResult()
            : [];

        $byAchievementId = [];
        foreach ($existing as $ap) {
            $achievement = $ap->getAchievement();
            if ($achievement !== null && $achievement->getId() !== null) {
                $byAchievementId[$achievement->getId()] = $ap;
            }
        }

        $em = $this->getEntityManager();
        foreach ($achievements as $achievement) {
            $id = $achievement->getId();
            if ($id === null || isset($byAchievementId[$id])) {
                continue;
            }
            $ap = new AchievementProgress();
            $ap->setUser($user)
                ->setAchievement($achievement);
            $em->persist($ap);
            $byAchievementId[$id] = $ap;
        }

        return $byAchievementId;
    }

    /**
     * @return AchievementProgress[]
     */
    public function findByUser(Users $user): array
    {
        return $this->findBy([
            'user' => $user,
        ]);
    }

    /**
     * Retourne l'achievement en cours le plus avancé (non complété) pour affichage dans le banner home.
     * Trie par (progress / triggerValue) DESC pour prioriser le plus proche de la complétion.
     */
    public function findMostAdvancedNotCompleted(Users $user): ?AchievementProgress
    {
        return $this->createQueryBuilder('ap')
            ->join('ap.achievement', 'a')
            ->addSelect('a')
            ->where('ap.user = :user')
            ->andWhere('ap.progress < a.triggerValue')
            ->andWhere('ap.progress > 0')
            ->orderBy('ap.progress / a.triggerValue', 'DESC')
            ->setMaxResults(1)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
