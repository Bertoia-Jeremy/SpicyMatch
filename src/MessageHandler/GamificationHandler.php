<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Gamification\GamificationManagerInterface;
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
        private readonly GamificationManagerInterface $manager,
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
        if ($spicyMatch === null) {
            $this->logger->warning('gamification.match_saved.spicy_match_missing', [
                'historyId' => $event->spicyMatchHistoryId,
            ]);

            return;
        }

        $user = $spicyMatch->getUser();
        if ($user === null) {
            return;
        }

        $progression = $this->manager->getOrCreateProgression($user);

        if (! $progression->isGamificationEnabled()) {
            return;
        }

        $this->em->wrapInTransaction(function () use ($event, $user, $progression): void {
            $this->manager->lockForUpdate($progression);

            if (! $this->processedEvents->claim($user, 'match_saved', 'match:' . $event->spicyMatchHistoryId)) {
                $this->logger->info('gamification.match_saved.duplicate', [
                    'userId' => $user->getId(),
                    'historyId' => $event->spicyMatchHistoryId,
                ]);

                return;
            }

            $progression->setTotalMatches($this->historyRepository->countByUser($user));
            $progression->setUniqueSpicesUsed($this->historyRepository->countDistinctSpicesByUser($user));

            $this->manager->process($progression, 'match_saved');

            $this->em->flush();
        });
    }
}
