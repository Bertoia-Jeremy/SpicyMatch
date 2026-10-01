<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Gamification\GamificationManagerInterface;
use App\Message\SpiceReadEvent;
use App\Repository\ProcessedGamificationEventRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpiceViewRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SpiceReadGamificationHandler
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly SpicesRepository $spicesRepository,
        private readonly SpiceViewRepository $spiceViewRepository,
        private readonly GamificationManagerInterface $manager,
        private readonly EntityManagerInterface $em,
        private readonly ProcessedGamificationEventRepository $processedEvents,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SpiceReadEvent $event): void
    {
        $user = $this->usersRepository->find($event->userId);
        if ($user === null) {
            return;
        }

        $progression = $this->manager->getOrCreateProgression($user);

        if (! $progression->isGamificationEnabled()) {
            return;
        }

        $this->em->wrapInTransaction(function () use ($event, $user, $progression): void {
            $this->manager->lockForUpdate($progression);

            $eventKey = sprintf('read:%d:%s', $event->spiceId, $this->clock->now()->format('Y-m-d'));
            if (! $this->processedEvents->claim($user, 'spice_read', $eventKey)) {
                $this->logger->info('gamification.spice_read.duplicate', [
                    'userId' => $user->getId(),
                    'spiceId' => $event->spiceId,
                ]);

                return;
            }

            $progression->setDiscoveries($this->spiceViewRepository->countDistinctSpicesByUser($user));
            $progression->setTotalSpicesRead($this->spiceViewRepository->countByUser($user));

            $stats = $this->manager->getOrCreateStats($user);
            $stats->recordVisitedSpice($event->spiceId);

            $spice = $this->spicesRepository->find($event->spiceId);
            if ($spice && ($group = $spice->getAromaticGroups()) && $group->getId()) {
                $stats->addVisitedAromaticGroup($group->getId());
            }

            $this->manager->process($progression, 'spice_read', [
                'isNewView' => $event->isNewViewToday,
            ]);

            $this->em->flush();
        });
    }
}
