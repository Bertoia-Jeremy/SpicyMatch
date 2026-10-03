<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\ValueObject\Education\BriefingFact;

final readonly class BriefingFacts
{
    public function __construct(
        private AcademyManager $academyManager,
    ) {
    }

    /**
     * @return list<BriefingFact>
     */
    public function for(GameMode $mode): array
    {
        $rounds = $mode->totalQuestions() ?? 0;

        return match ($mode) {
            GameMode::QCM => [
                $this->fact('fa-solid fa-list-ol', 'questions', static fn (): int => $rounds),
                $this->fact('fa-solid fa-bell-concierge', 'options', static fn (): int => QcmQuestionGenerator::OPTION_COUNT),
            ],
            GameMode::HANGMAN => [
                $this->fact('fa-solid fa-mortar-pestle', 'spices', static fn (): int => $rounds),
                $this->fact('fa-solid fa-heart', 'errors', $this->academyManager->getHangmanMaxErrors(...)),
            ],
            GameMode::GUESS_WHO => [
                $this->fact('fa-solid fa-mortar-pestle', 'spices', static fn (): int => $rounds),
                $this->fact('fa-solid fa-magnifying-glass', 'clues', $this->academyManager->getGuessWhoMaxClues(...)),
            ],
            GameMode::INTRUS => [
                $this->fact('fa-solid fa-list-ol', 'questions', static fn (): int => $rounds),
                $this->fact('fa-solid fa-mortar-pestle', 'per_question', static fn (): int => AcademyManager::INTRUS_OPTION_COUNT),
            ],
            GameMode::SURVIVAL => [
                $this->fact('fa-solid fa-heart', 'errors', static fn (): int => 0),
                $this->fact('fa-solid fa-bell-concierge', 'options', fn (GameDifficulty $d): int => $this->academyManager->getSurvivalOptionCounts($d)[0]),
                $this->fact('fa-solid fa-check', 'compatibles', fn (GameDifficulty $d): int => $this->academyManager->getSurvivalOptionCounts($d)[1]),
            ],
            GameMode::CHRONO => [
                $this->fact('fa-solid fa-stopwatch', 'time', $this->academyManager->getChronoTimeLimit(...), 'ui.edu.fact.unit_seconds'),
                $this->fact('fa-solid fa-bell-concierge', 'options', $this->academyManager->getChronoOptionsCount(...)),
                $this->fact('fa-solid fa-bolt', 'fast_bonus', fn (GameDifficulty $d): int => $this->academyManager->getChronoSpeedThresholds($d)[0], 'ui.edu.fact.unit_seconds'),
            ],
        };
    }

    /**
     * @param \Closure(GameDifficulty): int $value
     */
    private function fact(string $icon, string $key, \Closure $value, ?string $unit = null): BriefingFact
    {
        $values = [];
        foreach (GameDifficulty::cases() as $difficulty) {
            $values[$difficulty->value] = $value($difficulty);
        }

        return new BriefingFact($icon, 'ui.edu.fact.' . $key, $values, $unit);
    }
}
