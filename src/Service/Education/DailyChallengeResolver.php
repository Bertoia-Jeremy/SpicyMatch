<?php

declare(strict_types=1);

namespace App\Service\Education;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\GameMode;
use App\Service\Clock\GameDay;

final readonly class DailyChallengeResolver
{
    public function __construct(
        private GameDay $gameDay,
    ) {
    }

    public function forUser(?Users $user): ?GameMode
    {
        return $this->resolve($user, $this->gameDay->today());
    }

    public function forUserTomorrow(?Users $user): ?GameMode
    {
        return $this->resolve($user, $this->gameDay->tomorrow());
    }

    public function resolve(?Users $user, \DateTimeImmutable $day): ?GameMode
    {
        $progression = $user?->getProgression();
        if ($progression instanceof UserProgression && ! $progression->isGamificationEnabled()) {
            return null;
        }

        $level = $progression?->getLevel() ?? 1;
        $modes = array_values(array_filter(
            GameMode::cases(),
            static fn (GameMode $mode): bool => $mode->isEnabled() && $mode->isUnlockedForLevel($level),
        ));

        if ($modes === []) {
            return null;
        }

        return $modes[GameDay::ordinal($day) % \count($modes)];
    }
}
