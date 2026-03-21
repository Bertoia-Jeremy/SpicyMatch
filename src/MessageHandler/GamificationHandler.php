<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\UserProgression;
use App\Message\MatchSavedEvent;
use App\Repository\SpicyMatchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GamificationHandler
{
    public function __construct(
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly \App\Gamification\GamificationManagerInterface $manager,
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

        $progression = $user->getProgression();
        if ($progression === null) {
            $progression = new UserProgression();
            $progression->setUser($user);
            $user->setProgression($progression);
            $this->em->persist($progression);
        }

        if ($progression->isGamificationEnabled()) {
            // Idempotent: count from DB instead of incrementing (safe on Messenger retry)
            $matchCount = $this->historyRepository->countByUser($user);
            $progression->setTotalMatches($matchCount);

            $uniqueSpiceCount = $this->historyRepository->countDistinctSpicesByUser($user);
            $progression->setUniqueSpicesUsed($uniqueSpiceCount);
        }

        $this->manager->process($progression, 'match_saved');

        $this->em->flush();
    }
}
