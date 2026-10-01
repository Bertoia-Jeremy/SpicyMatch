<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;

final class ActivityStreakEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::ACTIVITY_STREAK;
    }

    public function eventTypes(): array
    {
        return ['spice_read', 'match_saved', 'game_completed', 'favorite_toggled'];
    }

    public function currentValue(UserProgression $progression, array $context): int
    {
        return $progression->getLongestActivityStreak();
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        return ContextFilter::matches($achievement, $context)
            && $progression->getLongestActivityStreak() >= $achievement->getTriggerValue();
    }
}
