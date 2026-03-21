<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\UserProgression;
use App\Message\SpiceReadEvent;
use App\Repository\SpicesRepository;
use App\Repository\SpiceViewRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {
    }

    public function __invoke(SpiceReadEvent $event): void
    {
        $user = $this->usersRepository->find($event->userId);
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

        // Only track if gamification is enabled
        if ($progression->isGamificationEnabled()) {
            if ($event->isNewViewToday) {
                $progression->incrementSpicesRead();
                $progression->recordReadingStreak();
            }

            // Idempotent: set discoveries from DB count (safe on Messenger retry)
            $distinctCount = $this->spiceViewRepository->countDistinctSpicesByUser($user);
            $progression->setDiscoveries($distinctCount);

            // Update stats
            $stats = $this->manager->getOrCreateStats($user);
            $stats->recordVisitedSpice($event->spiceId);

            $spice = $this->spicesRepository->find($event->spiceId);
            if ($spice && $group = $spice->getAromaticGroups()) {
                if ($group->getId()) {
                    $stats->addVisitedAromaticGroup($group->getId());
                }
            }
        }

        $this->manager->process($progression, 'spice_read', [
            'isNewView' => $event->isNewViewToday,
        ]);

        $this->em->flush();
    }
}
