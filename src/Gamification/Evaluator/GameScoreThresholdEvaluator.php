<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;

final class GameScoreThresholdEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::GAME_SCORE_THRESHOLD;
    }

    public function eventTypes(): array
    {
        return ['game_completed'];
    }

    public function currentValue(UserProgression $progression, array $context): int
    {
        return (int) ($context['score'] ?? 0);
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        if (! ContextFilter::matches($achievement, $context)) {
            return false;
        }

        if ($achievement->getContextGameMode() === null) {
            return false;
        }

        return ((int) ($context['score'] ?? 0)) >= $achievement->getTriggerValue();
    }
}
