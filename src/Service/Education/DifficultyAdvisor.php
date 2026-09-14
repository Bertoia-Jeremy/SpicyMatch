<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Entity\GameSession;
use App\Repository\GameSessionRepository;

final class DifficultyAdvisor
{
    public function __construct(
        private readonly GameSessionRepository $sessionRepository,
        private readonly SkillAssessor $assessor,
    ) {
    }

    public function adviseFor(GameSession $session): ?SkillAssessment
    {
        $user = $session->getUser();
        $mode = $session->getGameMode();

        if ($user === null || ! $mode->tracksAccuracy()) {
            return null;
        }

        $difficulty = $session->getDifficulty();

        return $this->assessor->assess(
            $difficulty,
            $this->sessionRepository->findRecentAccuracies($user, $mode, $difficulty, SkillAssessor::WINDOW),
        );
    }
}
