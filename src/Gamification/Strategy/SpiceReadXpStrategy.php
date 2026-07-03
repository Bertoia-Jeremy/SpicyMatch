<?php

declare(strict_types=1);

namespace App\Gamification\Strategy;

use App\Entity\UserProgression;
use App\Gamification\XpStrategyInterface;

final class SpiceReadXpStrategy implements XpStrategyInterface
{
    public function calculate(UserProgression $progression, array $context): int
    {
        // Only award XP on the first view of a spice for the day
        return ($context['isNewView'] ?? false) ? 5 : 0;
    }

    public function supports(string $eventType): bool
    {
        return 'spice_read' === $eventType;
    }
}
