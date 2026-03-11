<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\UserProgression;
use App\Gamification\GamificationEngine;
use App\Message\MatchSavedEvent;
use App\Repository\SpicyMatchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GamificationHandler
{
    public function __construct(
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly GamificationEngine $engine,
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

        $progression->incrementMatches();

        $spiceCount = $spicyMatch->getSpices()
            ->count();
        if ($spiceCount > $progression->getUniqueSpicesUsed()) {
            $progression->setUniqueSpicesUsed($spiceCount);
        }

        $this->engine->process($progression, 'match_saved');

        $this->em->flush();
    }
}
