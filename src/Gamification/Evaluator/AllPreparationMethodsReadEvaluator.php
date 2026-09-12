<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use App\Repository\PreparationMethodsRepository;
use App\Repository\SpiceViewRepository;

final class AllPreparationMethodsReadEvaluator implements TriggerEvaluatorInterface
{
    public function __construct(
        private readonly PreparationMethodsRepository $preparationMethodsRepository,
        private readonly SpiceViewRepository $spiceViewRepository,
    ) {
    }

    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::ALL_PREPARATION_METHODS_READ;
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
        if ($user === null) {
            return false;
        }

        $totalMethods = $this->preparationMethodsRepository->count([]);
        if ($totalMethods === 0) {
            return false;
        }

        return $this->spiceViewRepository->countDistinctPreparationMethodsSeenBy($user) >= $totalMethods;
    }
}
