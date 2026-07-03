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
    /**
     * Per-request cache: avoids N+1 when the same trigger is fetched multiple times
     * in one HTTP request (e.g. GamificationManager + AchievementChecker).
     *
     * @var array<string, Achievement[]>|null
     */
    private ?array $enabledByTrigger = null;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Achievement::class);
    }

    /**
     * @return Achievement[]
     */
    public function findByTrigger(AchievementTrigger $trigger): array
    {
        if (null === $this->enabledByTrigger || $this->cacheIsDetached()) {
            $this->warmEnabledCache();
        }

        return $this->enabledByTrigger[$trigger->value] ?? [];
    }

    private function cacheIsDetached(): bool
    {
        $em = $this->getEntityManager();
        foreach ($this->enabledByTrigger ?? [] as $group) {
            foreach ($group as $achievement) {
                return ! $em->contains($achievement);
            }
        }

        return false;
    }

    /**
     * Prime the per-request cache with all enabled achievements, grouped by trigger.
     * One SELECT instead of N (one per trigger).
     */
    private function warmEnabledCache(): void
    {
        $all = $this->createQueryBuilder('a')
            ->where('a.enabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult()
        ;

        $byTrigger = [];
        foreach ($all as $achievement) {
            $byTrigger[$achievement->getTrigger()->value][] = $achievement;
        }

        $this->enabledByTrigger = $byTrigger;
    }

    /**
     * Force cache invalidation (used after admin mutation via CRUD).
     */
    public function resetEnabledCache(): void
    {
        $this->enabledByTrigger = null;
    }

    /**
     * @return Achievement[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.rarity', 'ASC')
            ->addOrderBy('a.triggerValue', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
