<?php

declare(strict_types=1);

namespace App\Gamification;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Gamification\Evaluator\TriggerEvaluatorRegistry;
use App\Repository\AchievementRepository;

/**
 * Checks which achievements are newly unlocked for a given event.
 * Delegates per-trigger logic to TriggerEvaluator implementations (Strategy pattern).
 * Event → triggers mapping is derived from the evaluators themselves (see Registry::forEvent).
 */
final class AchievementChecker
{
    public function __construct(
        private readonly AchievementRepository $achievementRepository,
        private readonly TriggerEvaluatorRegistry $evaluators,
    ) {
    }

    /**
     * Returns achievements newly unlocked by this event (not already owned).
     *
     * @param array<string, mixed> $context
     *
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
