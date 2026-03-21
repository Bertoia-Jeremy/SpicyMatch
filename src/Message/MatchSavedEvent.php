<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched (async) when a SpicyMatchHistory is created.
 * Handled by GamificationHandler to update XP, totalMatches, and unlock achievements.
 */
final class MatchSavedEvent
{
    public function __construct(
        public readonly int $spicyMatchHistoryId,
        public readonly int $userId,
    ) {
    }
}
