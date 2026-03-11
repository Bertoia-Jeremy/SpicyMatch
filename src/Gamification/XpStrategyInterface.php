<?php

declare(strict_types=1);

namespace App\Gamification;

use App\Entity\UserProgression;

interface XpStrategyInterface
{
    /**
     * Calculate XP to award for the given event context.
     * Returns 0 if this strategy has nothing to award.
     *
     * @param array<string, mixed> $context
     */
    public function calculate(UserProgression $progression, array $context): int;

    public function supports(string $eventType): bool;
}
