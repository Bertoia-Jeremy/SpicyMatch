<?php

declare(strict_types=1);

namespace App\ValueObject\Education;

use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Enum\RecordState;
use App\Enum\ResultLexicon;
use App\Enum\ResultTier;

final readonly class GameResultView
{
    public const float RING_CIRCUMFERENCE = 452.39;

    public const int CHAIN_RING_TARGET = 10;

    /**
     * @param list<ResultAnswer> $answers
     * @param list<MissedSpice>  $missed
     */
    public function __construct(
        public GameMode $mode,
        public GameDifficulty $difficulty,
        public int $correct,
        public int $total,
        public float $accuracy,
        public int $score,
        public ?int $durationSeconds,
        public bool $dailyBonus,
        public ?XpProgress $xp,
        public ?int $previousBest,
        public ?RecordState $record,
        public array $answers,
        public array $missed,
        public ?NextStep $nextStep,
        public bool $canReplay,
    ) {
    }

    public function lexicon(): ResultLexicon
    {
        return $this->mode->resultLexicon();
    }

    public function isChain(): bool
    {
        return $this->lexicon() === ResultLexicon::CHAIN;
    }

    public function tier(): ResultTier
    {
        return $this->isChain()
            ? ResultTier::fromChainLength($this->correct)
            : ResultTier::fromAccuracy($this->accuracy);
    }

    public function accuracyPct(): ?int
    {
        return $this->isChain() ? null : (int) round($this->accuracy);
    }

    public function mistakes(): int
    {
        return max(0, $this->total - $this->correct);
    }

    public function wrongAnswers(): int
    {
        return \count(array_filter($this->answers, static fn (ResultAnswer $answer): bool => ! $answer->correct));
    }

    public function isPerfect(): bool
    {
        return ! $this->isChain() && $this->total > 0 && $this->correct >= $this->total;
    }

    public function ringRatio(): float
    {
        if ($this->isChain()) {
            return min($this->correct, self::CHAIN_RING_TARGET) / self::CHAIN_RING_TARGET;
        }

        return $this->total > 0 ? min(1.0, $this->correct / $this->total) : 0.0;
    }

    public function ringOffset(): float
    {
        return round(self::RING_CIRCUMFERENCE * (1 - $this->ringRatio()), 2);
    }

    public function celebration(): string
    {
        return $this->record?->isCelebrated() === true ? 'confetti' : 'sparks';
    }

    public function durationMinutes(): ?int
    {
        return $this->durationSeconds === null ? null : intdiv($this->durationSeconds, 60);
    }

    public function durationRemainder(): ?int
    {
        return $this->durationSeconds === null ? null : $this->durationSeconds % 60;
    }

    public function replayRoute(): string
    {
        return $this->mode->isLiveComponent() ? 'education_play_live' : 'education_briefing';
    }

    /**
     * @return array{mode: string, difficulty: string}
     */
    public function replayParams(?GameDifficulty $difficulty = null): array
    {
        return [
            'mode' => $this->mode->value,
            'difficulty' => ($difficulty ?? $this->difficulty)
                ->value,
        ];
    }
}
