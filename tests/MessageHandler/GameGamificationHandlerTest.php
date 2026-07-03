<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Gamification\GamificationManagerInterface;
use App\Message\GameCompletedEvent;
use App\MessageHandler\GameGamificationHandler;
use App\Repository\GameSessionRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class GameGamificationHandlerTest extends TestCase
{
    private UsersRepository&MockObject $usersRepo;

    private GameSessionRepository&MockObject $sessionRepo;

    private GamificationManagerInterface&MockObject $manager;

    private EntityManagerInterface&MockObject $em;

    private GameGamificationHandler $handler;

    protected function setUp(): void
    {
        $this->usersRepo = $this->createMock(UsersRepository::class);
        $this->sessionRepo = $this->createMock(GameSessionRepository::class);
        $this->manager = $this->createMock(GamificationManagerInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $processedEvents = $this->createMock(\App\Repository\ProcessedGamificationEventRepository::class);
        $processedEvents->method('claim')
            ->willReturn(true);
        $this->handler = new GameGamificationHandler(
            $this->usersRepo,
            $this->sessionRepo,
            $this->manager,
            $this->em,
            $processedEvents,
            new \Psr\Log\NullLogger(),
        );
    }

    public function testReturnsEarlyWhenUserNotFound(): void
    {
        $this->usersRepo->expects(self::once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->manager->expects(self::never())->method('process');

        ($this->handler)(new GameCompletedEvent(999, 1, 'qcm', 7, 10, 21));
    }

    public function testCreatesProgressionWhenNull(): void
    {
        $user = $this->createMock(Users::class);
        $this->usersRepo->method('find')
            ->willReturn($user);
        $this->sessionRepo->method('countFinishedByUser')
            ->willReturn(1);

        // Progression creation is now delegated to the manager.
        $this->manager->expects(self::once())
            ->method('getOrCreateProgression')
            ->with($user)
            ->willReturn(new UserProgression());

        $this->manager->expects(self::once())->method('process');

        ($this->handler)(new GameCompletedEvent(1, 1, 'qcm', 7, 10, 21));
    }

    public function testUsesIdempotentCountFromDatabase(): void
    {
        $progression = new UserProgression();
        $user = $this->createMock(Users::class);
        $this->manager->method('getOrCreateProgression')
            ->willReturn($progression);
        $this->usersRepo->method('find')
            ->willReturn($user);

        $this->sessionRepo->expects(self::once())
            ->method('countFinishedByUser')
            ->with($user)
            ->willReturn(5);

        $this->manager->expects(self::once())
            ->method('process')
            ->with($progression, 'game_completed', self::callback(fn (array $ctx) => 5 === $ctx['gamesCompleted']));

        ($this->handler)(new GameCompletedEvent(1, 1, 'qcm', 7, 10, 21));
    }

    public function testForwardsAllEventDataInContext(): void
    {
        $progression = new UserProgression();
        $user = $this->createMock(Users::class);
        $this->manager->method('getOrCreateProgression')
            ->willReturn($progression);
        $this->usersRepo->method('find')
            ->willReturn($user);
        $this->sessionRepo->method('countFinishedByUser')
            ->willReturn(3);

        $this->manager->expects(self::once())
            ->method('process')
            ->with(
                $progression,
                'game_completed',
                [
                    'xpEarned' => 42,
                    'gamesCompleted' => 3,
                    'gameMode' => 'qcm', // string, not enum
                    'correctAnswers' => 7,
                    'totalQuestions' => 10,
                    // `score` mirrors `xpEarned` so GameScoreThresholdEvaluator has a value to test against.
                    'score' => 42,
                ]
            );

        ($this->handler)(new GameCompletedEvent(1, 1, 'qcm', 7, 10, 42));
    }
}
