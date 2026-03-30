<?php

declare(strict_types=1);

namespace App\Enum;

enum GameMode: string
{
    case QCM = 'qcm';
    case SURVIVAL = 'survival';
    case GUESS_WHO = 'guess_who';
    case INTRUS = 'intrus';
    case HANGMAN = 'hangman';
    case CHRONO = 'chrono';

    public function label(): string
    {
        return match ($this) {
            self::QCM => 'Le Choix du Chef',
            self::SURVIVAL => 'Défi de Scoville',
            self::GUESS_WHO => 'Palais Fin',
            self::INTRUS => 'Hors Saison',
            self::HANGMAN => 'Cuisson en Cours',
            self::CHRONO => 'À Feu Vif',
        };
    }

    public function xpPerCorrect(): int
    {
        return match ($this) {
            self::QCM => 3,
            self::SURVIVAL => 5,
            self::GUESS_WHO => 4,
            self::INTRUS => 3,
            self::HANGMAN => 4,
            self::CHRONO => 3,
        };
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function isLiveComponent(): bool
    {
        return $this !== self::QCM;
    }

    public function description(): string
    {
        return match ($this) {
            self::QCM => 'Trouve l\'épice la plus compatible parmi 4 choix.',
            self::SURVIVAL => 'Enchaîne les épices compatibles sans erreur.',
            self::GUESS_WHO => 'Devine l\'épice à partir d\'indices progressifs.',
            self::INTRUS => 'Trouve l\'intrus parmi les épices compatibles.',
            self::HANGMAN => 'Devine le nom de l\'épice lettre par lettre.',
            self::CHRONO => 'Identifie les épices le plus vite possible.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::QCM => 'fa-solid fa-utensils',
            self::SURVIVAL => 'fa-solid fa-pepper-hot',
            self::GUESS_WHO => 'fa-solid fa-wine-glass',
            self::INTRUS => 'fa-solid fa-ban',
            self::HANGMAN => 'fa-solid fa-temperature-three-quarters',
            self::CHRONO => 'fa-solid fa-fire-flame-curved',
        };
    }

    public function totalQuestions(): ?int
    {
        return match ($this) {
            self::QCM, self::INTRUS => 10,
            self::GUESS_WHO, self::HANGMAN => 8,
            self::SURVIVAL, self::CHRONO => null,
        };
    }
}
