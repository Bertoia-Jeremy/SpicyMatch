<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\UserProgression;
use App\Gamification\GamificationManagerInterface;

/**
 * Null Object implementation for opt-out gamification.
 */
class NullGamificationManager implements GamificationManagerInterface
{
    public function process(UserProgression $progression, string $eventType, array $context = []): void
    {
        // Do nothing — opt-out or tracking disabled.
    }

    public function getOrCreateStats(\App\Entity\Users $user): \App\Entity\UserStat
    {
        return $user->getStats() ?? new \App\Entity\UserStat();
    }
}
