<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\UserProgression;
use App\Message\EasterEggFoundEvent;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EasterEggGamificationHandler
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly \App\Gamification\GamificationManagerInterface $manager,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(EasterEggFoundEvent $event): void
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

        $this->manager->process($progression, 'easter_egg_found', [
            'easterEggSlug' => $event->easterEggSlug,
            'xpAmount' => $event->xpAmount,
        ]);

        $this->em->flush();
    }
}
