<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use App\Repository\GameSessionRepository;

final class GameScoreThresholdEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function __construct(
        private readonly GameSessionRepository $gameSessionRepository,
    ) {
    }

    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::GAME_SCORE_THRESHOLD;
    }

    public function eventTypes(): array
    {
        return ['game_completed'];
    }

    public function currentValue(UserProgression $progression, array $context): int
    {
        return (int) ($context['score'] ?? 0);
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        if (! ContextFilter::matches($achievement, $context)) {
            return false;
        }

        $mode = $achievement->getContextGameMode();
        $group = $achievement->getContextAromaticGroup();
        $user = $progression->getUser();

        if (null === $user || null === $mode) {
            return false;
        }

        if (null !== $group) {
            $maxScore = $this->gameSessionRepository->maxScoreInModeForGroup(
                $user,
                $mode,
                $group,
                $achievement->getContextDifficulty(),
            );

            return $maxScore >= $achievement->getTriggerValue();
        }

        return ((int) ($context['score'] ?? 0)) >= $achievement->getTriggerValue();
    }
}
