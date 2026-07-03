<?php

declare(strict_types=1);

namespace App\Gamification\Strategy;

use App\Entity\UserProgression;
use App\Gamification\XpStrategyInterface;

final class MatchXpStrategy implements XpStrategyInterface
{
    public function calculate(UserProgression $progression, array $context): int
    {
        return 10;
    }

    public function supports(string $eventType): bool
    {
        return 'match_saved' === $eventType;
    }
}
