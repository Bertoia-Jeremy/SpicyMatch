<?php

declare(strict_types=1);

namespace App\Gamification;

use App\Entity\PendingGamificationNotification;
use App\Entity\UserProgression;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestrates XP calculation (Strategy pattern) and achievement unlocking.
 * Queues notifications for delivery on the next HTML response.
 */
final class GamificationEngine
{
    /**
     * @param iterable<XpStrategyInterface> $strategies Tagged with gamification.xp_strategy
     */
    public function __construct(
        private readonly iterable $strategies,
        private readonly AchievementChecker $achievementChecker,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function process(UserProgression $progression, string $eventType, array $context = []): void
    {
        if (! $progression->isGamificationEnabled()) {
            return;
        }

        $user = $progression->getUser();
        if ($user === null) {
            return;
        }

        $levelBefore = $progression->getLevel();

        // Apply XP strategies
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($eventType)) {
                $xp = $strategy->calculate($progression, $context);
                if ($xp > 0) {
                    $progression->addXp($xp);
                }
            }
        }

        // Check and unlock achievements
        $unlocked = $this->achievementChecker->check($progression, $eventType, $context);
        foreach ($unlocked as $achievement) {
            $progression->addXp($achievement->getXpReward());
            $this->em->persist(new PendingGamificationNotification($user, 'achievement_unlocked', [
                'slug' => $achievement->getSlug(),
                'name' => $achievement->getName(),
                'icon' => $achievement->getIcon(),
                'rarity' => $achievement->getRarity()
                    ->value,
                'label' => $achievement->getRarity()
                    ->label(),
                'xpReward' => $achievement->getXpReward(),
            ]));
        }

        // Notify on level-up
        $levelAfter = $progression->getLevel();
        if ($levelAfter > $levelBefore) {
            $this->em->persist(new PendingGamificationNotification($user, 'level_up', [
                'level' => $levelAfter,
            ]));
        }
    }
}
