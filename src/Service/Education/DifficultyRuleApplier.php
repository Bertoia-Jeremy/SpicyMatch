<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;

final class DifficultyRuleApplier
{
    private const int HANGMAN_BASE_SECONDS = 60;

    public function isMonochrome(GameDifficulty $difficulty): bool
    {
        return $difficulty === GameDifficulty::HARD;
    }

    public function hangmanTimeLimitSeconds(GameDifficulty $difficulty): int
    {
        return match ($difficulty) {
            GameDifficulty::EASY => 90,
            GameDifficulty::MEDIUM => self::HANGMAN_BASE_SECONDS,
            GameDifficulty::HARD => (int) round(self::HANGMAN_BASE_SECONDS * 0.7),
        };
    }

    public function label(GameDifficulty $difficulty): string
    {
        return match ($difficulty) {
            GameDifficulty::EASY => 'Commis',
            GameDifficulty::MEDIUM => 'Cuisinier',
            GameDifficulty::HARD => 'Chef de Partie',
        };
    }
}
