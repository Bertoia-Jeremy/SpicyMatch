<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;

final class ReadingStreakEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::READING_STREAK;
    }

    public function eventTypes(): array
    {
        return ['spice_read'];
    }

    public function currentValue(UserProgression $progression, array $context): int
    {
        return $progression->getLongestReadingStreak();
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        return ContextFilter::matches($achievement, $context)
            && $progression->getLongestReadingStreak() >= $achievement->getTriggerValue();
    }
}
