<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Gamification\GamificationManagerInterface;
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
        private readonly GamificationManagerInterface $manager,
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

        $progression = $this->manager->getOrCreateProgression($user);

        if (! $progression->isGamificationEnabled()) {
            return;
        }

        $this->em->wrapInTransaction(function () use ($event, $user, $progression): void {
            $this->manager->lockForUpdate($progression);

            if (! $this->processedEvents->claim($user, 'easter_egg_found', 'egg:' . $event->easterEggSlug)) {
                $this->logger->info('gamification.easter_egg.duplicate', [
                    'userId' => $user->getId(),
                    'slug' => $event->easterEggSlug,
                ]);

                return;
            }

            $this->manager->process($progression, 'easter_egg_found', [
                'easterEggSlug' => $event->easterEggSlug,
            ]);

            $this->em->flush();
        });
    }
}
