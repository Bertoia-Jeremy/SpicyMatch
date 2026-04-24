<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;

/**
 * Shared predicate: does the runtime event context satisfy the optional
 * gameMode / difficulty filters declared on the Achievement?
 *
 * A filter of null on the achievement means "wildcard".
 */
final class ContextFilter
{
    /**
     * @param array<string, mixed> $context
     */
    public static function matches(Achievement $achievement, array $context): bool
    {
        $expectedMode = $achievement->getContextGameMode();
        if ($expectedMode !== null && ! self::modeMatches($expectedMode, $context['gameMode'] ?? null)) {
            return false;
        }

        $expectedDifficulty = $achievement->getContextDifficulty();
        if ($expectedDifficulty !== null && ! self::difficultyMatches(
            $expectedDifficulty,
            $context['difficulty'] ?? null
        )) {
            return false;
        }

        return true;
    }

    private static function modeMatches(GameMode $expected, mixed $actual): bool
    {
        if ($actual instanceof GameMode) {
            return $actual === $expected;
        }

        if (\is_string($actual)) {
            return $actual === $expected->value;
        }

        return false;
    }

    private static function difficultyMatches(GameDifficulty $expected, mixed $actual): bool
    {
        if ($actual instanceof GameDifficulty) {
            return $actual === $expected;
        }

        if (\is_string($actual)) {
            return $actual === $expected->value;
        }

        return false;
    }
}
