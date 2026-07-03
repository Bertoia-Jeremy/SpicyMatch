<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;

/**
 * Définit les règles transverses associées à chaque niveau de difficulté.
 *
 * - Commis (EASY)         : aides visuelles complètes, chrono confortable, intrus faciles.
 * - Cuisinier (MEDIUM)    : comportement par défaut.
 * - Chef de Partie (HARD) : rendu monochrome, chrono Hangman -30 %, intrus stricts (score > 0 mais faible).
 *
 * Ce service ne fait que définir les règles. La propagation est garantie via :
 *  - une variable Twig globale (DifficultyExtension) pour le rendu,
 *  - une validation serveur des timers (GameSession::$expiresAt),
 *  - une clé de cache discriminée pour les intrus stricts.
 */
final class DifficultyRuleApplier
{
    private const int HANGMAN_BASE_SECONDS = 60;

    public function isMonochrome(GameDifficulty $difficulty): bool
    {
        return GameDifficulty::HARD === $difficulty;
    }

    public function hangmanTimeLimitSeconds(GameDifficulty $difficulty): int
    {
        return match ($difficulty) {
            GameDifficulty::EASY => 90,
            GameDifficulty::MEDIUM => self::HANGMAN_BASE_SECONDS,
            GameDifficulty::HARD => (int) round(self::HANGMAN_BASE_SECONDS * 0.7), // 42s
        };
    }

    public function intrusStrictMode(GameDifficulty $difficulty): bool
    {
        return GameDifficulty::HARD === $difficulty;
    }

    /**
     * Label "métier" affiché côté UX.
     */
    public function label(GameDifficulty $difficulty): string
    {
        return match ($difficulty) {
            GameDifficulty::EASY => 'Commis',
            GameDifficulty::MEDIUM => 'Cuisinier',
            GameDifficulty::HARD => 'Chef de Partie',
        };
    }
}
