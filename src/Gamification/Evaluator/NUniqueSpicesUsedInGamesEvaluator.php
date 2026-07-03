<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use App\Repository\GameSessionRepository;

final class NUniqueSpicesUsedInGamesEvaluator implements TriggerEvaluatorInterface
{
    public function __construct(
        private readonly GameSessionRepository $gameSessionRepository,
    ) {
    }

    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::N_UNIQUE_SPICES_USED_IN_GAMES;
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
        if (null === $user) {
            return false;
        }

        return $this->gameSessionRepository->countDistinctTargetSpices($user) >= $achievement->getTriggerValue();
    }
}
