<?php

declare(strict_types=1);

namespace App\Enum;

enum GameMode: string
{
    case QCM = 'qcm';
    case SURVIVAL = 'survival';
    case GUESS_WHO = 'guess_who';

    public function label(): string
    {
        return match ($this) {
            self::QCM => 'QCM - Mélange à trou',
            self::SURVIVAL => 'Mode Survie',
            self::GUESS_WHO => 'Guess Who',
        };
    }

    public function xpPerCorrect(): int
    {
        return match ($this) {
            self::QCM => 3,
            self::SURVIVAL => 5,
            self::GUESS_WHO => 4,
        };
    }

    public function isEnabled(): bool
    {
        return $this === self::QCM;
    }
}
