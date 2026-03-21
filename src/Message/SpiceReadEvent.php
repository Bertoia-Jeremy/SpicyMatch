<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SpiceReadEvent
{
    public function __construct(
        public int $userId,
        public int $spiceId,
        public bool $isNewViewToday,
    ) {
    }
}
