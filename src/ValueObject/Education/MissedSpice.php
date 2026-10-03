<?php

declare(strict_types=1);

namespace App\ValueObject\Education;

final readonly class MissedSpice
{
    public string $initial;

    public function __construct(
        public string $name,
        public string $slug,
        public ?string $contextName,
        public ?string $given,
    ) {
        $this->initial = mb_strtoupper(mb_substr($name, 0, 1));
    }

    public function contextInitial(): ?string
    {
        return $this->contextName === null ? null : mb_strtoupper(mb_substr($this->contextName, 0, 1));
    }
}
