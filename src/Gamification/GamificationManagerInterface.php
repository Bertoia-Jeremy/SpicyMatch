<?php

declare(strict_types=1);

namespace App\Gamification;

use App\Entity\UserProgression;

interface GamificationManagerInterface
{
    /**
     * Process an event for gamification.
     *
     * @param array<string, mixed> $context
     */
    public function process(UserProgression $progression, string $eventType, array $context = []): void;

    public function getOrCreateStats(\App\Entity\Users $user): \App\Entity\UserStat;
}
