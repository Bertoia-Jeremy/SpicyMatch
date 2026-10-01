<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\ContentKind;

final readonly class ContentReadEvent
{
    public function __construct(
        public int $userId,
        public ContentKind $kind,
    ) {
    }
}
