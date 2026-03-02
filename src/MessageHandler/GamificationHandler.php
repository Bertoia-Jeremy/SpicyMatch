<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use App\Message\MatchSavedEvent;
use App\Repository\AchievementRepository;
use App\Repository\SpicyMatchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GamificationHandler
{
    public function __construct(
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly AchievementRepository $achievementRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(MatchSavedEvent $event): void
    {
        $history = $this->historyRepository->find($event->spicyMatchHistoryId);
        if ($history === null) {
            return;
        }

        $spicyMatch = $history->getSpicyMatch();
        $user = $spicyMatch?->getUser();
        if ($user === null) {
            return;
        }

        // Get or create UserProgression
        $progression = $user->getProgression();
        if ($progression === null) {
            $progression = new UserProgression();
            $progression->setUser($user);
            $user->setProgression($progression);
            $this->em->persist($progression);
        }

        // Award +10 XP and increment match counter
        $progression->addXp(10)->incrementMatches();

        // Track unique spices used (simple max-based approach)
        $spiceCount = $spicyMatch->getSpices()->count();
        if ($spiceCount > $progression->getUniqueSpicesUsed()) {
            $progression->setUniqueSpicesUsed($spiceCount);
        }

        // Check and unlock achievements
        $triggersToCheck = [
            AchievementTrigger::FIRST_MATCH,
            AchievementTrigger::N_MATCHES,
            AchievementTrigger::N_SPICES_USED,
        ];

        foreach ($triggersToCheck as $trigger) {
            foreach ($this->achievementRepository->findByTrigger($trigger) as $achievement) {
                if ($progression->hasAchievement($achievement)) {
                    continue;
                }

                $unlocked = match ($trigger) {
                    AchievementTrigger::FIRST_MATCH   => $progression->getTotalMatches() >= 1,
                    AchievementTrigger::N_MATCHES     => $progression->getTotalMatches() >= $achievement->getTriggerValue(),
                    AchievementTrigger::N_SPICES_USED => $progression->getUniqueSpicesUsed() >= $achievement->getTriggerValue(),
                    default                           => false,
                };

                if ($unlocked) {
                    $progression->unlockAchievement($achievement);
                    $progression->addXp($achievement->getXpReward());
                }
            }
        }

        $this->em->flush();
    }
}
