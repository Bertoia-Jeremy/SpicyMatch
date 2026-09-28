<?php

declare(strict_types=1);

namespace App\Message;

final readonly class FavoriteToggledEvent
{
    public function __construct(
        public int $userId,
    ) {
    }
}
