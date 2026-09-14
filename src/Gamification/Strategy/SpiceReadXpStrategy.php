<?php

declare(strict_types=1);

namespace App\Gamification\Strategy;

use App\Entity\UserProgression;
use App\Gamification\XpStrategyInterface;

final class SpiceReadXpStrategy implements XpStrategyInterface
{
    public const int XP_PER_NEW_VIEW = 5;

    public function calculate(UserProgression $progression, array $context): int
    {
        return ($context['isNewView'] ?? false) ? self::XP_PER_NEW_VIEW : 0;
    }

    public function supports(string $eventType): bool
    {
        return $eventType === 'spice_read';
    }
}
