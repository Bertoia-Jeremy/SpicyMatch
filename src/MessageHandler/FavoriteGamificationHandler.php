<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use App\Message\FavoriteToggledEvent;
use App\Repository\AchievementRepository;
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
        private readonly AchievementRepository $achievementRepository,
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

        foreach ($this->achievementRepository->findByTrigger(AchievementTrigger::N_FAVORITES) as $achievement) {
            if ($progression->hasAchievement($achievement)) {
                continue;
            }

            if ($favoriteCount >= $achievement->getTriggerValue()) {
                $progression->unlockAchievement($achievement);
                $progression->addXp($achievement->getXpReward());
            }
        }

        $this->em->flush();
    }
}
