<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;

final readonly class SkillAssessment
{
    public function __construct(
        public GameDifficulty $current,
        public GameDifficulty $suggested,
        public float $winrate,
        public int $samples,
    ) {
    }

    public function isPromotion(): bool
    {
        return $this->suggested->rank() > $this->current->rank();
    }
}
