<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched (async) when a SpicyMatchHistory favorite is toggled ON.
 * Handled by FavoriteGamificationHandler to check N_FAVORITES achievements.
 */
final class FavoriteToggledEvent
{
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
