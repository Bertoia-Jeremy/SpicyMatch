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

    /**
     * Clé de traduction (domaine messages) — traduire à l'affichage via |trans.
     */
    public function label(): string
    {
        return 'enum.game_mode.' . $this->value . '.label';
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

    /**
     * Clé de traduction (domaine messages) — traduire à l'affichage via |trans.
     */
    public function description(): string
    {
        return 'enum.game_mode.' . $this->value . '.desc';
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
            self::QCM => 7,
            self::INTRUS => 7,
            self::GUESS_WHO, self::HANGMAN => 7,
            self::SURVIVAL, self::CHRONO => null,
        };
    }
}
