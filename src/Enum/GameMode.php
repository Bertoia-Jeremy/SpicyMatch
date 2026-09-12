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
            self::QCM => 5,
            self::INTRUS => 5,
            self::GUESS_WHO, self::HANGMAN => 5,
            self::SURVIVAL, self::CHRONO => null,
        };
    }

    /**
     * Clé de traduction (domaine messages) — traduire à l'affichage via |trans.
     */
    public function titleTop(): string
    {
        return 'enum.game_mode.' . $this->value . '.title_top';
    }

    /**
     * Clé de traduction (domaine messages) — traduire à l'affichage via |trans.
     */
    public function titleBottom(): string
    {
        return 'enum.game_mode.' . $this->value . '.title_bottom';
    }

    /**
     * Clé de traduction (domaine messages) — traduire à l'affichage via |trans.
     */
    public function tagline(): string
    {
        return 'enum.game_mode.' . $this->value . '.tagline';
    }

    /**
     * @param list<self> $modes
     */
    public static function dailyFeatured(array $modes): self
    {
        return $modes[(int) (new \DateTimeImmutable('today'))->format('z') % \count($modes)];
    }

    public function posterGradient(): string
    {
        return match ($this) {
            self::QCM => 'radial-gradient(ellipse at 50% 0%, #4D7C0F 0%, #2d4a08 60%, #1a2a04 100%)',
            self::SURVIVAL => 'radial-gradient(ellipse at 50% 0%, #C04020 0%, #7a1a1a 60%, #3a0a0a 100%)',
            self::GUESS_WHO => 'radial-gradient(ellipse at 50% 0%, #C98A4B 0%, #7d4a1c 60%, #331a06 100%)',
            self::INTRUS => 'radial-gradient(ellipse at 50% 0%, #5B4636 0%, #33241a 60%, #160e09 100%)',
            self::HANGMAN => 'radial-gradient(ellipse at 50% 0%, #D97706 0%, #7c3a04 60%, #3a1c02 100%)',
            self::CHRONO => 'radial-gradient(ellipse at 50% 0%, #A3324C 0%, #5e1428 60%, #280611 100%)',
        };
    }
}
