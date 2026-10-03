<?php

declare(strict_types=1);

namespace App\ValueObject\Education;

final readonly class BriefingView
{
    /**
     * @param list<BriefingFact> $facts
     * @param list<string>       $rules
     */
    public function __construct(
        public array $facts,
        public array $rules,
        public bool $levelLocked,
        public int $playedToday,
        public int $maxDailySessions,
        public bool $premium,
        public int $premiumMaxDailySessions,
        public int $maxXpPerSession,
        public bool $dailyBonus,
        public ?int $best,
    ) {
    }

    public function remainingToday(): int
    {
        return max(0, $this->maxDailySessions - $this->playedToday);
    }
}
