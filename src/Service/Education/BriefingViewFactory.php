<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Entity\Users;
use App\Enum\GameMode;
use App\Repository\GameSessionRepository;
use App\ValueObject\Education\BriefingView;

final readonly class BriefingViewFactory
{
    public function __construct(
        private BriefingFacts $facts,
        private AcademyManager $academyManager,
        private GameSessionManager $sessionManager,
        private GameSessionRepository $sessionRepository,
    ) {
    }

    public function build(Users $user, GameMode $mode): BriefingView
    {
        return new BriefingView(
            facts: $this->facts->for($mode),
            rules: array_values($this->academyManager->getRulesFor($mode)),
            levelLocked: ! $mode->isUnlockedForLevel($user->getProgression()?->getLevel() ?? 1),
            playedToday: $this->sessionManager->countTodaySessions($user, $mode),
            maxDailySessions: $this->sessionManager->maxDailySessions($user),
            premium: $user->isPremium(),
            premiumMaxDailySessions: GameSessionManager::MAX_DAILY_SESSIONS_PREMIUM,
            maxXpPerSession: GameSessionManager::MAX_XP_PER_SESSION,
            dailyBonus: $this->sessionManager->qualifiesForDailyBonus($user, $mode),
            best: $this->sessionRepository->findBestScoreByUserGrouped($user)[$mode->value] ?? null,
        );
    }
}
