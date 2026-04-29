<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;

final class FirstGameEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::FIRST_GAME;
    }

    public function eventTypes(): array
    {
        return ['game_completed'];
    }

    public function currentValue(UserProgression $progression, array $context): int
    {
        return (int) ($context['gamesCompleted'] ?? 0);
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        return ContextFilter::matches($achievement, $context)
            && ((int) ($context['gamesCompleted'] ?? 0)) >= 1;
    }
}
