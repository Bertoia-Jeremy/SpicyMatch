<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\AchievementTrigger;
use App\Enum\GameMode;
use App\Repository\GameSessionRepository;

final readonly class GamePerfectRunEvaluator implements TriggerEvaluatorInterface
{
    public function __construct(
        private GameSessionRepository $gameSessionRepository,
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

        if (! $user instanceof Users || ! $mode instanceof GameMode) {
            return false;
        }

        return $this->gameSessionRepository->countPerfectRunsByMode($user, $mode) >= $achievement->getTriggerValue();
    }
}
