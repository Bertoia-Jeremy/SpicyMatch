<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;

final class EasterEggFoundEvaluator implements TriggerEvaluatorInterface
{
    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::EASTER_EGG_FOUND;
    }

    public function eventTypes(): array
    {
        return ['easter_egg_found'];
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        if (! ContextFilter::matches($achievement, $context)) {
            return false;
        }

        return ($context['easterEggSlug'] ?? '') === $achievement->getEasterEggSlug();
    }
}
