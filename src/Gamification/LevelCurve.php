<?php

declare(strict_types=1);

namespace App\Gamification;

final class LevelCurve
{
    private const float EXPONENT = 1.3;

    private const int BASE_XP = 100;

    public static function levelFor(int $xp): int
    {
        if ($xp <= 0) {
            return 1;
        }

        return max(1, (int) floor(($xp / self::BASE_XP) ** (1 / self::EXPONENT)));
    }

    public static function thresholdFor(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        return (int) ceil(self::BASE_XP * $level ** self::EXPONENT);
    }

    public static function sqlLevel(string $column): string
    {
        return \sprintf(
            'GREATEST(1, FLOOR(POW(GREATEST(%s, 0) / %dE0, 1E0 / %sE0)))',
            $column,
            self::BASE_XP,
            self::EXPONENT,
        );
    }
}
