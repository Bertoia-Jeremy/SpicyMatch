<?php

declare(strict_types=1);

namespace App\Gamification\Strategy;

use App\Entity\UserProgression;
use App\Gamification\XpStrategyInterface;

final class EasterEggXpStrategy implements XpStrategyInterface
{
    public function calculate(UserProgression $progression, array $context): int
    {
        return (int) ($context['xpAmount'] ?? 75);
    }

    public function supports(string $eventType): bool
    {
        return $eventType === 'easter_egg_found';
    }
}
