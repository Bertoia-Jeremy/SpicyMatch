<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Gamification\GamificationManagerInterface;
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
        private readonly GamificationManagerInterface $manager,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(FavoriteToggledEvent $event): void
    {
        $user = $this->usersRepository->find($event->userId);
        if ($user === null) {
            return;
        }

        $progression = $this->manager->getOrCreateProgression($user);

        if (! $progression->isGamificationEnabled()) {
            return;
        }

        $favoriteCount = $this->historyRepository->countFavoritesByUser($user);

        $this->em->wrapInTransaction(function () use ($favoriteCount, $progression): void {
            $this->manager->lockForUpdate($progression);

            $this->manager->process($progression, 'favorite_toggled', [
                'favoriteCount' => $favoriteCount,
            ]);

            $this->em->flush();
        });

        $this->logger->info('gamification.favorite_toggled.processed', [
            'userId' => $user->getId(),
            'favoriteCount' => $favoriteCount,
        ]);
    }
}
