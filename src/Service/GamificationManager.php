<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PendingGamificationNotification;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Entity\UserStat;
use App\Gamification\AchievementChecker;
use App\Gamification\Evaluator\ProgressTrackableEvaluator;
use App\Gamification\Evaluator\TriggerEvaluatorRegistry;
use App\Gamification\GamificationManagerInterface;
use App\Gamification\XpStrategyInterface;
use App\Repository\AchievementProgressRepository;
use App\Repository\AchievementRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsAlias(GamificationManagerInterface::class)]
class GamificationManager implements GamificationManagerInterface
{
    /**
     * @param iterable<XpStrategyInterface> $strategies
     */
    public function __construct(
        #[AutowireIterator('gamification.xp_strategy')]
        private readonly iterable $strategies,
        private readonly AchievementChecker $achievementChecker,
        private readonly EntityManagerInterface $em,
        private readonly AchievementRepository $achievementRepository,
        private readonly AchievementProgressRepository $achievementProgressRepository,
        private readonly TriggerEvaluatorRegistry $evaluators,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getOrCreateProgression(Users $user): UserProgression
    {
        $progression = $user->getProgression();
        if ($progression === null) {
            $progression = new UserProgression();
            $progression->setUser($user);
            $user->setProgression($progression);
            $this->em->persist($progression);
        }

        return $progression;
    }

    public function getOrCreateStats(Users $user): UserStat
    {
        $stats = $user->getStats();
        if ($stats === null) {
            $stats = new UserStat();
            $stats->setUser($user);
            $user->setStats($stats);
            $this->em->persist($stats);
        }

        return $stats;
    }

    public function lockForUpdate(UserProgression $progression): void
    {
        if ($progression->getId() === null) {
            $this->em->flush();
        }

        $this->em->lock($progression, LockMode::PESSIMISTIC_WRITE);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function process(UserProgression $progression, string $eventType, array $context = []): void
    {
        if (! $progression->isGamificationEnabled()) {
            return;
        }

        $user = $progression->getUser();
        if ($user === null) {
            return;
        }

        $this->getOrCreateStats($user);

        $levelBefore = $progression->getLevel();

        $standardXp = 0;
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($eventType)) {
                $xp = $strategy->calculate($progression, $context);
                if ($xp > 0) {
                    $progression->addXp($xp);
                    $standardXp += $xp;
                }
            }
        }

        if ($standardXp > 0) {
            $this->em->persist(new PendingGamificationNotification($user, 'xp_gained', [
                'amount' => $standardXp,
                'source' => $this->sourceLabelFor($eventType),
            ]));
        }

        $unlocked = $this->achievementChecker->check($progression, $eventType, $context);
        foreach ($unlocked as $achievement) {
            $progression->addXp($achievement->getXpReward());
            $this->em->persist(new PendingGamificationNotification($user, 'achievement_unlocked', [
                'slug' => $achievement->getSlug(),
                'name' => $achievement->getLocalizedName($user->getLocale()),
                'icon' => $achievement->getIcon(),
                'rarity' => $achievement->getRarity()
                    ->value,
                'label' => $achievement->getRarity()
                    ->label(),
                'xpReward' => $achievement->getXpReward(),
            ]));

            $this->logger->info('gamification.achievement_unlocked', [
                'userId' => $user->getId(),
                'slug' => $achievement->getSlug(),
                'rarity' => $achievement->getRarity()
                    ->value,
                'xp' => $achievement->getXpReward(),
                'eventType' => $eventType,
            ]);
        }

        $this->updateAchievementProgress($progression, $eventType, $context);

        $levelAfter = $progression->getLevel();
        if ($levelAfter > $levelBefore) {
            $this->em->persist(new PendingGamificationNotification($user, 'level_up', [
                'level' => $levelAfter,
            ]));

            $this->logger->info('gamification.level_up', [
                'userId' => $user->getId(),
                'from' => $levelBefore,
                'to' => $levelAfter,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function updateAchievementProgress(UserProgression $progression, string $eventType, array $context): void
    {
        $user = $progression->getUser();
        if ($user === null) {
            return;
        }

        $progressTargets = [];
        $achievementsToLoad = [];
        foreach ($this->evaluators->forEvent($eventType) as $evaluator) {
            if (! $evaluator instanceof ProgressTrackableEvaluator) {
                continue;
            }

            $currentValue = $evaluator->currentValue($progression, $context);

            foreach ($this->achievementRepository->findByTrigger($evaluator->trigger()) as $achievement) {
                if ($progression->hasAchievement($achievement)) {
                    continue;
                }
                $progressTargets[] = [$achievement, $currentValue];
                $achievementsToLoad[] = $achievement;
            }
        }

        if ($achievementsToLoad === []) {
            return;
        }

        $byAchievementId = $this->achievementProgressRepository->findOrCreateBatchForUser($user, $achievementsToLoad);

        foreach ($progressTargets as [$achievement, $value]) {
            $id = $achievement->getId();
            if ($id !== null && isset($byAchievementId[$id])) {
                $byAchievementId[$id]->setProgress($value);
            }
        }
    }

    private function sourceLabelFor(string $eventType): string
    {
        return match ($eventType) {
            'match_saved' => 'gamification.source.match_saved',
            'spice_read' => 'gamification.source.spice_read',
            'easter_egg_found' => 'gamification.source.easter_egg_found',
            'favorite_toggled' => 'gamification.source.favorite_toggled',
            'game_completed' => 'gamification.source.game_completed',
            default => '',
        };
    }
}
