<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Gamification\GamificationManagerInterface;
use App\Message\GameCompletedEvent;
use App\Repository\GameSessionRepository;
use App\Repository\ProcessedGamificationEventRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GameGamificationHandler
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly GameSessionRepository $sessionRepository,
        private readonly GamificationManagerInterface $manager,
        private readonly EntityManagerInterface $em,
        private readonly ProcessedGamificationEventRepository $processedEvents,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GameCompletedEvent $event): void
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

            if (! $this->processedEvents->claim($user, GameCompletedEvent::TYPE, GameCompletedEvent::processedKey($event->sessionId))) {
                $this->logger->info('gamification.game_completed.duplicate', [
                    'userId' => $user->getId(),
                    'sessionId' => $event->sessionId,
                ]);

                return;
            }

            $this->manager->process($progression, 'game_completed', [
                'xpEarned' => $event->xpEarned,
                'gamesCompleted' => $this->sessionRepository->countFinishedByUser($user),
                'gameMode' => $event->gameMode,
                'correctAnswers' => $event->correctAnswers,
                'totalQuestions' => $event->totalQuestions,
                'score' => $event->xpEarned,
                'dailyBonus' => $event->dailyBonus,
            ]);

            $this->em->flush();
        });
    }
}
