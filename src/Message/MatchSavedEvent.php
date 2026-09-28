<?php

declare(strict_types=1);

namespace App\Message;

final readonly class MatchSavedEvent
{
    public function __construct(
        public int $spicyMatchHistoryId,
        public int $userId,
    ) {
    }
}
