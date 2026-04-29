<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;

final class FirstDiscoveryEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::FIRST_DISCOVERY;
    }

    public function eventTypes(): array
    {
        return ['spice_read'];
    }

    public function currentValue(UserProgression $progression, array $context): int
    {
        return $progression->getDiscoveries();
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        return ContextFilter::matches($achievement, $context)
            && $progression->getDiscoveries() >= $achievement->getTriggerValue();
    }
}
