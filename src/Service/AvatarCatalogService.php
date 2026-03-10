<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\UserProgression;

/**
 * Catalogue des avatars disponibles.
 * Chaque avatar est un cercle coloré avec une icône FontAwesome.
 * Unlock conditions: 'default' | 'level' (int) | 'achievement' (slug string)
 */
class AvatarCatalogService
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const CATALOG = [
        // --- Starter (disponibles dès l'inscription) ---
        'seedling' => [
            'label'       => 'Explorateur',
            'icon'        => 'fa-seedling',
            'bg'          => '#4ade80',
            'text'        => '#14532d',
            'unlockType'  => 'default',
            'unlockValue' => null,
            'unlockLabel' => null,
        ],
        'mortar' => [
            'label'       => 'Apprenti',
            'icon'        => 'fa-mortar-pestle',
            'bg'          => '#c4a26b',
            'text'        => '#3b1f07',
            'unlockType'  => 'default',
            'unlockValue' => null,
            'unlockLabel' => null,
        ],
        'leaf' => [
            'label'       => 'Herboriste',
            'icon'        => 'fa-leaf',
            'bg'          => '#86efac',
            'text'        => '#064e3b',
            'unlockType'  => 'default',
            'unlockValue' => null,
            'unlockLabel' => null,
        ],
        // --- Niveau 3+ ---
        'fire' => [
            'label'       => 'Pimenté',
            'icon'        => 'fa-fire',
            'bg'          => '#fb923c',
            'text'        => '#7c2d12',
            'unlockType'  => 'level',
            'unlockValue' => 3,
            'unlockLabel' => 'Niveau 3 requis',
        ],
        'sun' => [
            'label'       => 'Soleil',
            'icon'        => 'fa-sun',
            'bg'          => '#fbbf24',
            'text'        => '#713f12',
            'unlockType'  => 'level',
            'unlockValue' => 3,
            'unlockLabel' => 'Niveau 3 requis',
        ],
        // --- Niveau 5+ ---
        'flask' => [
            'label'       => 'Alchimiste',
            'icon'        => 'fa-flask',
            'bg'          => '#a78bfa',
            'text'        => '#2e1065',
            'unlockType'  => 'level',
            'unlockValue' => 5,
            'unlockLabel' => 'Niveau 5 requis',
        ],
        'crown' => [
            'label'       => 'Maître épicier',
            'icon'        => 'fa-crown',
            'bg'          => '#f59e0b',
            'text'        => '#451a03',
            'unlockType'  => 'level',
            'unlockValue' => 5,
            'unlockLabel' => 'Niveau 5 requis',
        ],
        // --- Niveau 10+ ---
        'dragon' => [
            'label'       => 'Légende',
            'icon'        => 'fa-dragon',
            'bg'          => '#ef4444',
            'text'        => '#450a0a',
            'unlockType'  => 'level',
            'unlockValue' => 10,
            'unlockLabel' => 'Niveau 10 requis',
        ],
        'gem' => [
            'label'       => 'Diamant',
            'icon'        => 'fa-gem',
            'bg'          => '#22d3ee',
            'text'        => '#083344',
            'unlockType'  => 'level',
            'unlockValue' => 10,
            'unlockLabel' => 'Niveau 10 requis',
        ],
        // --- Achievements ---
        'compass' => [
            'label'       => 'Découvreur',
            'icon'        => 'fa-compass',
            'bg'          => '#60a5fa',
            'text'        => '#1e3a5f',
            'unlockType'  => 'achievement',
            'unlockValue' => 'first_discovery',
            'unlockLabel' => 'Succès "Premier pas" requis',
        ],
        'boxes' => [
            'label'       => 'Collectionneur',
            'icon'        => 'fa-boxes-stacked',
            'bg'          => '#34d399',
            'text'        => '#064e3b',
            'unlockType'  => 'achievement',
            'unlockValue' => 'n_spices_10',
            'unlockLabel' => 'Succès "Collectionneur" requis',
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getCatalog(): array
    {
        return self::CATALOG;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAvatar(string $slug): ?array
    {
        return self::CATALOG[$slug] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultAvatar(): array
    {
        return self::CATALOG['seedling'];
    }

    public function isUnlocked(string $slug, ?UserProgression $progression): bool
    {
        $avatar = self::CATALOG[$slug] ?? null;
        if ($avatar === null) {
            return false;
        }

        return match ($avatar['unlockType']) {
            'default'     => true,
            'level'       => $progression !== null && $progression->getLevel() >= $avatar['unlockValue'],
            'achievement' => $progression !== null && $this->hasAchievement($progression, (string) $avatar['unlockValue']),
            default       => false,
        };
    }

    private function hasAchievement(UserProgression $progression, string $achievementSlug): bool
    {
        foreach ($progression->getUserAchievements() as $ua) {
            if ($ua->getAchievement()->getSlug() === $achievementSlug) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getCatalogWithStatus(?UserProgression $progression): array
    {
        $result = [];
        foreach (self::CATALOG as $slug => $data) {
            $result[$slug] = $data + ['unlocked' => $this->isUnlocked($slug, $progression)];
        }

        return $result;
    }
}
