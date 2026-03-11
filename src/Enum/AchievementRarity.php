<?php

declare(strict_types=1);

namespace App\Enum;

enum AchievementRarity: string
{
    case COMMON = 'common';
    case RARE = 'rare';
    case EPIC = 'epic';
    case LEGENDARY = 'legendary';

    public function label(): string
    {
        return match ($this) {
            self::COMMON => 'Graine',
            self::RARE => 'Infusion',
            self::EPIC => 'Extraction',
            self::LEGENDARY => 'Essence',
        };
    }
}
