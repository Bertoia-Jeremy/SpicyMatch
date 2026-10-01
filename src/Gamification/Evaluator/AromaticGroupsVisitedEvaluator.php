<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Entity\UserStat;
use App\Enum\AchievementTrigger;
use App\Repository\AromaticGroupsRepository;

final readonly class AromaticGroupsVisitedEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function __construct(
        private AromaticGroupsRepository $aromaticGroupsRepository,
    ) {
    }

    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::AROMATIC_GROUPS_VISITED;
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
        if (! $stats instanceof UserStat) {
            return false;
        }

        $totalGroups = $this->aromaticGroupsRepository->count([]);
        if ($totalGroups === 0) {
            return false;
        }

        return $stats->visitedGroupsCount >= min(max(1, $achievement->getTriggerValue()), $totalGroups);
    }
}
