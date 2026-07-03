<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\FavoriteToggledEvent;
use App\Repository\SpicyMatchHistoryRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FavoriteGamificationHandler
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly \App\Gamification\GamificationManagerInterface $manager,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(FavoriteToggledEvent $event): void
    {
        $user = $this->usersRepository->find($event->userId);
        if (null === $user) {
            return;
        }

        $progression = $this->manager->getOrCreateProgression($user);

        // Count is DB-derived, so this handler is naturally idempotent on retry.
        $favoriteCount = $this->historyRepository->countFavoritesByUser($user);

        $this->manager->process($progression, 'favorite_toggled', [
            'favoriteCount' => $favoriteCount,
        ]);

        $this->em->flush();

        $this->logger->info('gamification.favorite_toggled.processed', [
            'userId' => $user->getId(),
            'favoriteCount' => $favoriteCount,
        ]);
    }
}
