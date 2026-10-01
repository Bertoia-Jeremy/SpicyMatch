<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;

final class FirstManualMatchEvaluator implements TriggerEvaluatorInterface
{
    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::FIRST_MANUAL_MATCH;
    }

    public function eventTypes(): array
    {
        return ['match_saved'];
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        return ContextFilter::matches($achievement, $context)
            && ($context['isManual'] ?? false) === true;
    }
}
