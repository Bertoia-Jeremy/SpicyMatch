<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\UserProgression;
use App\Message\MatchSavedEvent;
use App\Repository\ProcessedGamificationEventRepository;
use App\Repository\SpicyMatchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GamificationHandler
{
    public function __construct(
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly \App\Gamification\GamificationManagerInterface $manager,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly ProcessedGamificationEventRepository $processedEvents,
    ) {
    }

    public function __invoke(MatchSavedEvent $event): void
    {
        $history = $this->historyRepository->find($event->spicyMatchHistoryId);
        if ($history === null) {
            $this->logger->warning('gamification.match_saved.history_missing', [
                'historyId' => $event->spicyMatchHistoryId,
            ]);

            return;
        }

        $spicyMatch = $history->getSpicyMatch();
        $user = $spicyMatch?->getUser();
        if ($user === null) {
            return;
        }

        // Idempotence guard — short-circuit if this exact match has already been processed.
        if (! $this->processedEvents->claim($user, 'match_saved', 'match:' . $event->spicyMatchHistoryId)) {
            $this->logger->info('gamification.match_saved.duplicate', [
                'userId' => $user->getId(),
                'historyId' => $event->spicyMatchHistoryId,
            ]);

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
            // Recount from DB (keeps totals in sync even if ledger gets pruned).
            $matchCount = $this->historyRepository->countByUser($user);
            $progression->setTotalMatches($matchCount);

            $uniqueSpiceCount = $this->historyRepository->countDistinctSpicesByUser($user);
            $progression->setUniqueSpicesUsed($uniqueSpiceCount);
        }

        $this->manager->process($progression, 'match_saved');

        $this->em->flush();
    }
}
