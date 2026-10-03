<?php

declare(strict_types=1);

namespace App\ValueObject\Education;

use App\Gamification\LevelCurve;

final readonly class XpProgress
{
    public int $level;

    public int $floorXp;

    public int $nextXp;

    public int $remaining;

    public bool $leveledUp;

    public function __construct(
        public int $gained,
        public int $total,
        public bool $pending = false,
    ) {
        $this->level = LevelCurve::levelFor($total);
        $this->floorXp = LevelCurve::thresholdFor($this->level);
        $this->nextXp = LevelCurve::thresholdFor($this->level + 1);
        $this->remaining = max(0, $this->nextXp - $total);
        $this->leveledUp = $gained > 0 && LevelCurve::levelFor(max(0, $total - $gained)) < $this->level;
    }
}
