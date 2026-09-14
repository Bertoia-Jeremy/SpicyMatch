<?php

declare(strict_types=1);

namespace App\Gamification;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Gamification\Evaluator\TriggerEvaluatorRegistry;
use App\Repository\AchievementRepository;

final class AchievementChecker
{
    public function __construct(
        private readonly AchievementRepository $achievementRepository,
        private readonly TriggerEvaluatorRegistry $evaluators,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return Achievement[]
     */
    public function check(UserProgression $progression, string $eventType, array $context): array
    {
        $unlocked = [];

        foreach ($this->evaluators->forEvent($eventType) as $evaluator) {
            $trigger = $evaluator->trigger();
            foreach ($this->achievementRepository->findByTrigger($trigger) as $achievement) {
                if ($progression->hasAchievement($achievement)) {
                    continue;
                }
                if ($evaluator->isMet($achievement, $progression, $context)) {
                    $progression->unlockAchievement($achievement);
                    $unlocked[] = $achievement;
                }
            }
        }

        return $unlocked;
    }
}
