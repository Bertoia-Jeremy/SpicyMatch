<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Entity\GameSession;
use App\Entity\Users;
use App\Repository\GameSessionRepository;

final readonly class DifficultyAdvisor
{
    public function __construct(
        private GameSessionRepository $sessionRepository,
        private SkillAssessor $assessor,
    ) {
    }

    public function adviseFor(GameSession $session): ?SkillAssessment
    {
        $user = $session->getUser();
        $mode = $session->getGameMode();

        if (! $user instanceof Users || ! $mode->tracksAccuracy()) {
            return null;
        }

        $difficulty = $session->getDifficulty();

        return $this->assessor->assess(
            $difficulty,
            $this->sessionRepository->findRecentAccuracies($user, $mode, $difficulty, SkillAssessor::WINDOW),
        );
    }
}
