<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class PageTrail
{
    public function __construct(
        public string $route,
        public string $label,
    ) {
    }
}
