<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Enum\AchievementRarity;
use Twig\Attribute\AsTwigFunction;

final class RarityExtension
{
    /**
     * @var array<string, array{bg: string, text: string, ring: string}>
     */
    private const array COLORS = [
        'common' => [
            'bg' => '#f5f5f4',
            'text' => '#78716c',
            'ring' => '#a8a29e',
        ],
        'rare' => [
            'bg' => '#dbeafe',
            'text' => '#1d4ed8',
            'ring' => '#3b82f6',
        ],
        'epic' => [
            'bg' => '#f3e8ff',
            'text' => '#7e22ce',
            'ring' => '#a855f7',
        ],
        'legendary' => [
            'bg' => '#fef9c3',
            'text' => '#a16207',
            'ring' => '#eab308',
        ],
    ];

    private const array FALLBACK = [
        'bg' => '#fff7ed',
        'text' => '#9a3412',
        'ring' => '#f59e0b',
    ];

    /**
     * @return array{bg: string, text: string, ring: string}
     */
    #[AsTwigFunction(name: 'rarity_colors')]
    public function rarityColors(AchievementRarity|string|null $rarity): array
    {
        $key = $rarity instanceof AchievementRarity ? $rarity->value : (string) $rarity;

        return self::COLORS[$key] ?? self::FALLBACK;
    }
}
