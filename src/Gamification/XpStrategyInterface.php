<?php

declare(strict_types=1);

namespace App\Gamification;

use App\Entity\UserProgression;

interface XpStrategyInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function calculate(UserProgression $progression, array $context): int;

    public function supports(string $eventType): bool;
}
