<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\UserProgression;
use App\Gamification\GamificationEngine;
use App\Message\FavoriteToggledEvent;
use App\Repository\SpicyMatchHistoryRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class FavoriteGamificationHandler
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly GamificationEngine $engine,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(FavoriteToggledEvent $event): void
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

        $favoriteCount = $this->historyRepository->countFavoritesByUser($user);

        $this->engine->process($progression, 'favorite_toggled', [
            'favoriteCount' => $favoriteCount,
        ]);

        $this->em->flush();
    }
}
