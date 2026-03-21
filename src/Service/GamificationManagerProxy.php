<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\UserProgression;
use App\Gamification\GamificationManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Proxy to choose between real and null gamification manager based on user preference.
 */
#[AsAlias(GamificationManagerInterface::class)]
class GamificationManagerProxy implements GamificationManagerInterface
{
    public function __construct(
        private readonly GamificationManager $realManager,
        private readonly NullGamificationManager $nullManager,
    ) {
    }

    public function process(UserProgression $progression, string $eventType, array $context = []): void
    {
        $manager = $progression->isGamificationEnabled() ? $this->realManager : $this->nullManager;
        $manager->process($progression, $eventType, $context);
    }

    public function getOrCreateStats(\App\Entity\Users $user): \App\Entity\UserStat
    {
        $enabled = $user->getProgression()?->isGamificationEnabled() ?? true;
        $manager = $enabled ? $this->realManager : $this->nullManager;

        return $manager->getOrCreateStats($user);
    }
}
