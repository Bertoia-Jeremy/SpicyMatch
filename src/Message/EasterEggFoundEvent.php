<?php

declare(strict_types=1);

namespace App\Message;

final readonly class EasterEggFoundEvent
{
    public function __construct(
        public int $userId,
        public string $easterEggSlug,
        public int $xpAmount = 75,
    ) {
    }
}
