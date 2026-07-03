<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use App\Repository\SpiceViewRepository;

final class GroupMasteryReadEvaluator implements TriggerEvaluatorInterface
{
    public function __construct(
        private readonly SpiceViewRepository $spiceViewRepository,
    ) {
    }

    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::GROUP_MASTERY_READ;
    }

    public function eventTypes(): array
    {
        return ['spice_read'];
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        if (! ContextFilter::matches($achievement, $context)) {
            return false;
        }

        $user = $progression->getUser();
        $group = $achievement->getContextAromaticGroup();

        if (null === $user || null === $group) {
            return false;
        }

        return $this->spiceViewRepository->countByGroup($user, $group) >= $achievement->getTriggerValue();
    }
}
