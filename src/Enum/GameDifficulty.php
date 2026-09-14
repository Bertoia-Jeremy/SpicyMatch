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
        return 'enum.difficulty.' . $this->value;
    }

    public function xpMultiplier(): float
    {
        return match ($this) {
            self::EASY => 1.0,
            self::MEDIUM => 1.5,
            self::HARD => 2.0,
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::EASY => 1,
            self::MEDIUM => 2,
            self::HARD => 3,
        };
    }

    public function harder(): ?self
    {
        return match ($this) {
            self::EASY => self::MEDIUM,
            self::MEDIUM => self::HARD,
            self::HARD => null,
        };
    }

    public function easier(): ?self
    {
        return match ($this) {
            self::EASY => null,
            self::MEDIUM => self::EASY,
            self::HARD => self::MEDIUM,
        };
    }
}
