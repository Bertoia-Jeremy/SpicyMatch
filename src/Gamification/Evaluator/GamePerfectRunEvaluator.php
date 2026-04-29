<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use App\Repository\GameSessionRepository;

final class GamePerfectRunEvaluator implements TriggerEvaluatorInterface
{
    public function __construct(
        private readonly GameSessionRepository $gameSessionRepository,
    ) {
    }

    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::GAME_PERFECT_RUN;
    }

    public function eventTypes(): array
    {
        return ['game_completed'];
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        if (! ContextFilter::matches($achievement, $context)) {
            return false;
        }

        $user = $progression->getUser();
        $mode = $achievement->getContextGameMode();

        if ($user === null || $mode === null) {
            return false;
        }

        return $this->gameSessionRepository->countPerfectRunsByMode($user, $mode) >= $achievement->getTriggerValue();
    }
}
