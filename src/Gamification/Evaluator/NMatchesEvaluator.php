<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;

final class NMatchesEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::N_MATCHES;
    }

    public function eventTypes(): array
    {
        return ['match_saved'];
    }

    public function currentValue(UserProgression $progression, array $context): int
    {
        return $progression->getTotalMatches();
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        return ContextFilter::matches($achievement, $context)
            && $progression->getTotalMatches() >= $achievement->getTriggerValue();
    }
}
