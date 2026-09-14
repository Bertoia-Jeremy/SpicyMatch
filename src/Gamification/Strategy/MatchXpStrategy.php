<?php

declare(strict_types=1);

namespace App\Gamification\Strategy;

use App\Entity\UserProgression;
use App\Gamification\XpStrategyInterface;

final class MatchXpStrategy implements XpStrategyInterface
{
    public const int XP_PER_MATCH = 10;

    public function calculate(UserProgression $progression, array $context): int
    {
        return self::XP_PER_MATCH;
    }

    public function supports(string $eventType): bool
    {
        return $eventType === 'match_saved';
    }
}
