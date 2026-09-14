<?php

declare(strict_types=1);

namespace App\Message;

final class MatchSavedEvent
{
    public function __construct(
        public readonly int $spicyMatchHistoryId,
        public readonly int $userId,
    ) {
    }
}
