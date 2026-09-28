<?php

declare(strict_types=1);

namespace App\Gamification;

use App\Entity\UserProgression;

interface GamificationManagerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function process(UserProgression $progression, string $eventType, array $context = []): void;

    public function getOrCreateProgression(\App\Entity\Users $user): UserProgression;

    public function getOrCreateStats(\App\Entity\Users $user): \App\Entity\UserStat;

    public function lockForUpdate(UserProgression $progression): void;
}
