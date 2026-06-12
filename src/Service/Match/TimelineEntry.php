<?php

declare(strict_types=1);

namespace App\Service\Match;

final readonly class TimelineEntry
{
    public function __construct(
        public int $id,
        public string $name,
        public ?float $retention,
        public ?string $kinetics,
    ) {
    }
}
