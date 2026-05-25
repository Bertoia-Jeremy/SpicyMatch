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

    /**
     * Fetch or lazily create a UserProgression for the given user.
     * Registers the new entity with the UoW (persist) but does NOT flush.
     */
    public function getOrCreateProgression(\App\Entity\Users $user): UserProgression;

    public function getOrCreateStats(\App\Entity\Users $user): \App\Entity\UserStat;
}
