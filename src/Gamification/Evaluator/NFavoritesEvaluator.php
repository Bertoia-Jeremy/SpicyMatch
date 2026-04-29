<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;

final class NFavoritesEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::N_FAVORITES;
    }

    public function eventTypes(): array
    {
        return ['favorite_toggled'];
    }

    public function currentValue(UserProgression $progression, array $context): int
    {
        return (int) ($context['favoriteCount'] ?? 0);
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        return ContextFilter::matches($achievement, $context)
            && ((int) ($context['favoriteCount'] ?? 0)) >= $achievement->getTriggerValue();
    }
}
