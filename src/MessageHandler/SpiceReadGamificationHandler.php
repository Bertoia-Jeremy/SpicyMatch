<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SpiceReadEvent;
use App\Repository\ProcessedGamificationEventRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpiceViewRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SpiceReadGamificationHandler
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly SpicesRepository $spicesRepository,
        private readonly SpiceViewRepository $spiceViewRepository,
        private readonly \App\Gamification\GamificationManagerInterface $manager,
        private readonly EntityManagerInterface $em,
        private readonly ProcessedGamificationEventRepository $processedEvents,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SpiceReadEvent $event): void
    {
        $user = $this->usersRepository->find($event->userId);
        if (null === $user) {
            return;
        }

        // Idempotence — one award per (user, spice, day). Retries of the same event
        // never double-count, but a new day = new event key = fresh award.
        $eventKey = sprintf('read:%d:%s', $event->spiceId, date('Y-m-d'));
        if (! $this->processedEvents->claim($user, 'spice_read', $eventKey)) {
            $this->logger->info('gamification.spice_read.duplicate', [
                'userId' => $user->getId(),
                'spiceId' => $event->spiceId,
            ]);

            return;
        }

        $progression = $this->manager->getOrCreateProgression($user);

        if ($progression->isGamificationEnabled()) {
            // Idempotent counters: recompute from DB, no raw incrementSpicesRead().
            $distinctCount = $this->spiceViewRepository->countDistinctSpicesByUser($user);
            $progression->setDiscoveries($distinctCount);
            $progression->setTotalSpicesRead($this->spiceViewRepository->countByUser($user));

            if ($event->isNewViewToday) {
                $progression->recordReadingStreak();
            }

            // Stats side: record visited spice + aromatic group.
            $stats = $this->manager->getOrCreateStats($user);
            $stats->recordVisitedSpice($event->spiceId);

            $spice = $this->spicesRepository->find($event->spiceId);
            if ($spice && ($group = $spice->getAromaticGroups()) && $group->getId()) {
                $stats->addVisitedAromaticGroup($group->getId());
            }
        }

        $this->manager->process($progression, 'spice_read', [
            'isNewView' => $event->isNewViewToday,
        ]);

        $this->em->flush();
    }
}
