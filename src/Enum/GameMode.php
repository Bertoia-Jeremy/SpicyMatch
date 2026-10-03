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
        return 'enum.game_mode.' . $this->value . '.label';
    }

    public function xpPerCorrect(): int
    {
        return match ($this) {
            self::QCM => 3,
            self::SURVIVAL => 5,
            self::GUESS_WHO => 4,
            self::INTRUS => 3,
            self::HANGMAN => 8,
            self::CHRONO => 3,
        };
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function requiredLevel(): int
    {
        return match ($this) {
            self::QCM => 1,
            self::HANGMAN => 1,
            self::GUESS_WHO => 2,
            self::INTRUS => 3,
            self::SURVIVAL => 5,
            self::CHRONO => 8,
        };
    }

    public function isUnlockedForLevel(int $level): bool
    {
        return $level >= $this->requiredLevel();
    }

    public function isLiveComponent(): bool
    {
        return $this !== self::QCM;
    }

    public function tracksAccuracy(): bool
    {
        return $this !== self::SURVIVAL;
    }

    public function resultLexicon(): ResultLexicon
    {
        return match ($this) {
            self::QCM => ResultLexicon::PAIRING,
            self::SURVIVAL => ResultLexicon::CHAIN,
            self::GUESS_WHO, self::INTRUS, self::HANGMAN, self::CHRONO => ResultLexicon::SPICE,
        };
    }

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
            self::QCM => 5,
            self::INTRUS => 5,
            self::GUESS_WHO, self::HANGMAN => 5,
            self::SURVIVAL, self::CHRONO => null,
        };
    }

    public function titleTop(): string
    {
        return 'enum.game_mode.' . $this->value . '.title_top';
    }

    public function titleBottom(): string
    {
        return 'enum.game_mode.' . $this->value . '.title_bottom';
    }

    public function tagline(): string
    {
        return 'enum.game_mode.' . $this->value . '.tagline';
    }

    public function promise(): string
    {
        return 'enum.game_mode.' . $this->value . '.promise';
    }

    public function isFullscreen(): bool
    {
        return $this === self::HANGMAN;
    }
}
