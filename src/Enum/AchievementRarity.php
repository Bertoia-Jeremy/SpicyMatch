<?php

declare(strict_types=1);

namespace App\Enum;

enum AchievementRarity: string
{
    case COMMON = 'common';
    case RARE = 'rare';
    case EPIC = 'epic';
    case LEGENDARY = 'legendary';

    /**
     * Clé de traduction (domaine messages) — traduire à l'affichage via |trans.
     */
    public function label(): string
    {
        return 'enum.rarity.'.$this->value;
    }
}
