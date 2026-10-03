<?php

declare(strict_types=1);

namespace App\ValueObject\Education;

final readonly class ResultAnswer
{
    public function __construct(
        public int $number,
        public ?string $title,
        public ?string $given,
        public ?string $expected,
        public bool $correct,
    ) {
    }
}
