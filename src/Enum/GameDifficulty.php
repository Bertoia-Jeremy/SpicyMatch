<?php

declare(strict_types=1);

namespace App\Enum;

enum GameDifficulty: string
{
    case EASY = 'easy';
    case MEDIUM = 'medium';
    case HARD = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::EASY => 'Facile',
            self::MEDIUM => 'Moyen',
            self::HARD => 'Difficile',
        };
    }

    public function xpMultiplier(): float
    {
        return match ($this) {
            self::EASY => 1.0,
            self::MEDIUM => 1.5,
            self::HARD => 2.0,
        };
    }
}
