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
        if (null === $user) {
            return;
        }

        // Idempotence — each finished GameSession is awarded once.
        if (! $this->processedEvents->claim($user, 'game_completed', 'session:'.$event->sessionId)) {
            $this->logger->info('gamification.game_completed.duplicate', [
                'userId' => $user->getId(),
                'sessionId' => $event->sessionId,
            ]);

            return;
        }

        $progression = $this->manager->getOrCreateProgression($user);

        // Idempotent: count total finished sessions from DB
        $gamesCompleted = $this->sessionRepository->countFinishedByUser($user);

        $this->manager->process($progression, 'game_completed', [
            'xpEarned' => $event->xpEarned,
            'gamesCompleted' => $gamesCompleted,
            'gameMode' => $event->gameMode,
            'correctAnswers' => $event->correctAnswers,
            'totalQuestions' => $event->totalQuestions,
            'score' => $event->xpEarned,
        ]);

        $this->em->flush();
    }
}
