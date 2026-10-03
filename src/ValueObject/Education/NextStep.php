<?php

declare(strict_types=1);

namespace App\ValueObject\Education;

use App\Enum\GameDifficulty;

final readonly class NextStep
{
    public function __construct(
        public GameDifficulty $difficulty,
        public bool $promotion,
        public ?int $winratePct = null,
        public ?int $samples = null,
    ) {
    }
}
