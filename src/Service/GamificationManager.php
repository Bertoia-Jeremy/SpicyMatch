<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PendingGamificationNotification;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Entity\UserStat;
use App\Enum\AchievementTrigger;
use App\Gamification\AchievementChecker;
use App\Gamification\XpStrategyInterface;
use App\Repository\AchievementProgressRepository;
use App\Repository\AchievementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Centralized Gamification Manager.
 * Handles XP calculation, achievement unlocking, and notifications.
 */
class GamificationManager implements \App\Gamification\GamificationManagerInterface
{
    /**
     * @param iterable<XpStrategyInterface> $strategies
     */
    public function __construct(
        #[TaggedIterator('gamification.xp_strategy')]
        private readonly iterable $strategies,
        private readonly AchievementChecker $achievementChecker,
        private readonly EntityManagerInterface $em,
        private readonly AchievementRepository $achievementRepository,
        private readonly AchievementProgressRepository $achievementProgressRepository,
    ) {
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

    /**
     * @param array<string, mixed> $context
     */
    public function process(UserProgression $progression, string $eventType, array $context = []): void
    {
        // Null Object / Opt-out check
        if (! $progression->isGamificationEnabled()) {
            return;
        }

        $user = $progression->getUser();
        if ($user === null) {
            return;
        }

        // Ensure UserStat exists
        $this->getOrCreateStats($user);

        $levelBefore = $progression->getLevel();

        // Apply XP strategies
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($eventType)) {
                $xp = $strategy->calculate($progression, $context);
                if ($xp > 0) {
                    $progression->addXp($xp);
                }
            }
        }

        // Check and unlock achievements
        $unlocked = $this->achievementChecker->check($progression, $eventType, $context);
        foreach ($unlocked as $achievement) {
            $progression->addXp($achievement->getXpReward());
            $this->em->persist(new PendingGamificationNotification($user, 'achievement_unlocked', [
                'slug' => $achievement->getSlug(),
                'name' => $achievement->getName(),
                'icon' => $achievement->getIcon(),
                'rarity' => $achievement->getRarity()
                    ->value,
                'label' => $achievement->getRarity()
                    ->label(),
                'xpReward' => $achievement->getXpReward(),
            ]));
        }

        // Update achievement progress bars
        $this->updateAchievementProgress($progression, $eventType, $context);

        // Notify on level-up
        $levelAfter = $progression->getLevel();
        if ($levelAfter > $levelBefore) {
            $this->em->persist(new PendingGamificationNotification($user, 'level_up', [
                'level' => $levelAfter,
            ]));
        }
    }

    /**
     * Upsert AchievementProgress for all achievements related to the current event triggers.
     *
     * @param array<string, mixed> $context
     */
    private function updateAchievementProgress(UserProgression $progression, string $eventType, array $context): void
    {
        $user = $progression->getUser();
        if ($user === null) {
            return;
        }

        $triggers = $this->triggersForEvent($eventType);

        foreach ($triggers as $trigger) {
            $currentValue = $this->getCurrentValue($trigger, $progression, $context);
            if ($currentValue === null) {
                continue;
            }

            foreach ($this->achievementRepository->findByTrigger($trigger) as $achievement) {
                if ($progression->hasAchievement($achievement)) {
                    continue; // already unlocked, skip
                }

                $ap = $this->achievementProgressRepository->findOrCreateForUser($user, $achievement);
                $ap->setProgress($currentValue);
            }
        }
    }

    /**
     * @return AchievementTrigger[]
     */
    private function triggersForEvent(string $eventType): array
    {
        return match ($eventType) {
            'match_saved' => [
                AchievementTrigger::FIRST_MATCH,
                AchievementTrigger::N_MATCHES,
                AchievementTrigger::N_SPICES_USED,
            ],
            'spice_read' => [
                AchievementTrigger::SPICE_READ,
                AchievementTrigger::READING_STREAK,
                AchievementTrigger::FIRST_DISCOVERY,
            ],
            'favorite_toggled' => [AchievementTrigger::N_FAVORITES],
            'easter_egg_found' => [AchievementTrigger::EASTER_EGG_FOUND],
            'game_completed' => [AchievementTrigger::FIRST_GAME, AchievementTrigger::N_GAMES_COMPLETED],
            default => [],
        };
    }

    private function getCurrentValue(AchievementTrigger $trigger, UserProgression $progression, array $context): ?int
    {
        return match ($trigger) {
            AchievementTrigger::FIRST_MATCH, AchievementTrigger::N_MATCHES => $progression->getTotalMatches(),
            AchievementTrigger::N_SPICES_USED => $progression->getUniqueSpicesUsed(),
            AchievementTrigger::N_FAVORITES => $context['favoriteCount'] ?? null,
            AchievementTrigger::SPICE_READ => $progression->getTotalSpicesRead(),
            AchievementTrigger::READING_STREAK => $progression->getLongestReadingStreak(),
            AchievementTrigger::FIRST_DISCOVERY => $progression->getDiscoveries(),
            AchievementTrigger::FIRST_GAME, AchievementTrigger::N_GAMES_COMPLETED => $context['gamesCompleted'] ?? null,
            AchievementTrigger::EASTER_EGG_FOUND, AchievementTrigger::ALL_TERPENES_VISITED => null,
        };
    }
}
