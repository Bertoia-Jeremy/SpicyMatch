<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use App\Repository\AromaticGroupsRepository;

final class AllTerpenesVisitedEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function __construct(
        private readonly AromaticGroupsRepository $aromaticGroupsRepository,
    ) {
    }

    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::ALL_TERPENES_VISITED;
    }

    public function eventTypes(): array
    {
        return ['spice_read'];
    }

    public function currentValue(UserProgression $progression, array $context): int
    {
        $stats = $progression->getUser()?->getStats();

        return $stats->visitedGroupsCount ?? 0;
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        if (! ContextFilter::matches($achievement, $context)) {
            return false;
        }

        $stats = $progression->getUser()?->getStats();
        if (null === $stats) {
            return false;
        }

        $totalGroups = $this->aromaticGroupsRepository->count([]);
        if (0 === $totalGroups) {
            return false;
        }

        return $stats->visitedGroupsCount >= $totalGroups;
    }
}
