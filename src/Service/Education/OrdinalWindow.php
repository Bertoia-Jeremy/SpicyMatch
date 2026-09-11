<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;

final class OrdinalWindow
{
    /**
     * @template TEntry of array<string, mixed>
     *
     * @param list<TEntry> $ordered
     *
     * @return list<TEntry>
     */
    public static function select(array $ordered, GameDifficulty $difficulty, int $need): array
    {
        $total = count($ordered);
        $size = max($need, (int) ceil($total / 3));

        $window = match ($difficulty) {
            GameDifficulty::EASY => array_slice($ordered, 0, $size),
            GameDifficulty::MEDIUM => array_slice($ordered, $size, $size),
            GameDifficulty::HARD => array_slice($ordered, max(0, $total - $size)),
        };

        return count($window) < $need ? $ordered : $window;
    }
}
