<?php

declare(strict_types=1);

namespace App\Gamification\Strategy;

use App\Entity\UserProgression;
use App\Gamification\XpStrategyInterface;

final class GameXpStrategy implements XpStrategyInterface
{
    public function calculate(UserProgression $progression, array $context): int
    {
        return $context['xpEarned'] ?? 0;
    }

    public function supports(string $eventType): bool
    {
        return $eventType === 'game_completed';
    }
}
