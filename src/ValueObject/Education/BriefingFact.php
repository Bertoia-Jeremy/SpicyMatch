<?php

declare(strict_types=1);

namespace App\ValueObject\Education;

use App\Enum\GameDifficulty;

final readonly class BriefingFact
{
    /**
     * @param array<string, int> $values
     */
    public function __construct(
        public string $icon,
        public string $label,
        public array $values,
        public ?string $unit = null,
    ) {
    }

    public function valueFor(GameDifficulty $difficulty): int
    {
        return $this->values[$difficulty->value];
    }
}
