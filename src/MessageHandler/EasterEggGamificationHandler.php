<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\EasterEggFoundEvent;
use App\Repository\ProcessedGamificationEventRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class EasterEggGamificationHandler
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly \App\Gamification\GamificationManagerInterface $manager,
        private readonly EntityManagerInterface $em,
        private readonly ProcessedGamificationEventRepository $processedEvents,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(EasterEggFoundEvent $event): void
    {
        $user = $this->usersRepository->find($event->userId);
        if ($user === null) {
            return;
        }

        // Idempotence — one award per (user, slug).
        if (! $this->processedEvents->claim($user, 'easter_egg_found', 'egg:' . $event->easterEggSlug)) {
            $this->logger->info('gamification.easter_egg.duplicate', [
                'userId' => $user->getId(),
                'slug' => $event->easterEggSlug,
            ]);

            return;
        }

        $progression = $this->manager->getOrCreateProgression($user);

        $this->manager->process($progression, 'easter_egg_found', [
            'easterEggSlug' => $event->easterEggSlug,
            'xpAmount' => $event->xpAmount,
        ]);

        $this->em->flush();
    }
}
