<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Enum\AchievementRarity;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Single source of truth for rarity → color mapping.
 * Used by _avatar.html.twig, dashboard, profile, achievements — everywhere a rarity pill renders.
 */
final class RarityExtension extends AbstractExtension
{
    /**
     * @var array<string, array{bg: string, text: string, ring: string}>
     */
    private const COLORS = [
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

    private const FALLBACK = [
        'bg' => '#fff7ed',
        'text' => '#9a3412',
        'ring' => '#f59e0b',
    ];

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('rarity_colors', $this->rarityColors(...))];
    }

    /**
     * @return array{bg: string, text: string, ring: string}
     */
    public function rarityColors(AchievementRarity|string|null $rarity): array
    {
        $key = $rarity instanceof AchievementRarity ? $rarity->value : (string) $rarity;

        return self::COLORS[$key] ?? self::FALLBACK;
    }
}
