<?php

declare(strict_types=1);

namespace App\Gamification;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use App\Repository\AchievementRepository;
use App\Repository\AromaticGroupsRepository;

/**
 * Checks which achievements are newly unlocked for a given event.
 */
final class AchievementChecker
{
    public function __construct(
        private readonly AchievementRepository $achievementRepository,
        private readonly AromaticGroupsRepository $aromaticGroupsRepository,
    ) {
    }

    /**
     * Returns achievements newly unlocked by this event (not already owned).
     *
     * @param array<string, mixed> $context
     *
     * @return Achievement[]
     */
    public function check(UserProgression $progression, string $eventType, array $context): array
    {
        $triggers = $this->triggersForEvent($eventType);
        $unlocked = [];

        foreach ($triggers as $trigger) {
            foreach ($this->achievementRepository->findByTrigger($trigger) as $achievement) {
                if ($progression->hasAchievement($achievement)) {
                    continue;
                }
                if ($this->isMet($achievement, $progression, $context)) {
                    $progression->unlockAchievement($achievement);
                    $unlocked[] = $achievement;
                }
            }
        }

        return $unlocked;
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
                AchievementTrigger::ALL_TERPENES_VISITED,
            ],
            'favorite_toggled' => [AchievementTrigger::N_FAVORITES],
            'easter_egg_found' => [AchievementTrigger::EASTER_EGG_FOUND],
            'game_completed' => [AchievementTrigger::FIRST_GAME, AchievementTrigger::N_GAMES_COMPLETED],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        return match ($achievement->getTrigger()) {
            AchievementTrigger::FIRST_MATCH => $progression->getTotalMatches() >= 1,
            AchievementTrigger::N_MATCHES => $progression->getTotalMatches() >= $achievement->getTriggerValue(),
            AchievementTrigger::N_SPICES_USED => $progression->getUniqueSpicesUsed() >= $achievement->getTriggerValue(),
            AchievementTrigger::N_FAVORITES => ($context['favoriteCount'] ?? 0) >= $achievement->getTriggerValue(),
            AchievementTrigger::SPICE_READ => $progression->getTotalSpicesRead() >= $achievement->getTriggerValue(),
            AchievementTrigger::READING_STREAK => $progression->getLongestReadingStreak() >= $achievement->getTriggerValue(),
            AchievementTrigger::FIRST_DISCOVERY => $progression->getDiscoveries() >= $achievement->getTriggerValue(),
            AchievementTrigger::EASTER_EGG_FOUND => ($context['easterEggSlug'] ?? '') === $achievement->getEasterEggSlug(),
            AchievementTrigger::ALL_TERPENES_VISITED => $this->checkAllTerpenesVisited($progression),
            AchievementTrigger::FIRST_GAME => ($context['gamesCompleted'] ?? 0) >= 1,
            AchievementTrigger::N_GAMES_COMPLETED => ($context['gamesCompleted'] ?? 0) >= $achievement->getTriggerValue(),
        };
    }

    private function checkAllTerpenesVisited(UserProgression $progression): bool
    {
        $stats = $progression->getUser()?->getStats();
        if ($stats === null) {
            return false;
        }

        $totalGroups = $this->aromaticGroupsRepository->count([]);
        if ($totalGroups === 0) {
            return false;
        }

        // Using PHP 8.4 Property Hook visitedGroupsCount
        return $stats->visitedGroupsCount >= $totalGroups;
    }
}
