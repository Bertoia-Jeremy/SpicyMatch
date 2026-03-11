<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\UserProgression;
use App\Gamification\GamificationEngine;
use App\Message\SpiceReadEvent;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SpiceReadGamificationHandler
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly GamificationEngine $engine,
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

        if ($event->isNewViewToday) {
            $progression->incrementSpicesRead();
            $progression->recordReadingStreak();
        }

        $this->engine->process($progression, 'spice_read', [
            'isNewView' => $event->isNewViewToday,
        ]);

        $this->em->flush();
    }
}
