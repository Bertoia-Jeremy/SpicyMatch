<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Entity\GameSession;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\GameSessionRepository;
use App\Service\Education\GameSessionManager;
use App\Service\Education\QuestionGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class GameSessionManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private GameSessionRepository&MockObject $sessionRepo;
    private MessageBusInterface&MockObject $bus;
    private QuestionGeneratorInterface&MockObject $generator;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->sessionRepo = $this->createMock(GameSessionRepository::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->generator = $this->createMock(QuestionGeneratorInterface::class);
    }

    private function makeManager(): GameSessionManager
    {
        return new GameSessionManager($this->em, $this->sessionRepo, $this->bus, [$this->generator]);
    }

    public function testStartSessionCreatesAndPersists(): void
    {
        $user = $this->createStub(Users::class);
        $user->method('getId')
            ->willReturn(1);

        $this->sessionRepo->expects(self::once())
            ->method('countTodayByUser')
            ->with($user, GameMode::QCM)
            ->willReturn(0);

        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(GameSession::class));

        $this->em->expects(self::once())
            ->method('flush');

        $manager = $this->makeManager();
        $session = $manager->startSession($user, GameMode::QCM, GameDifficulty::EASY);

        self::assertSame(GameMode::QCM, $session->getGameMode());
        self::assertSame(GameDifficulty::EASY, $session->getDifficulty());
    }

    public function testStartSessionThrowsOnDailyLimit(): void
    {
        $user = $this->createStub(Users::class);

        $this->sessionRepo->expects(self::once())
            ->method('countTodayByUser')
            ->willReturn(5);

        $manager = $this->makeManager();

        $this->expectException(\RuntimeException::class);
        $manager->startSession($user, GameMode::QCM, GameDifficulty::EASY);
    }

    public function testNextQuestionDelegatesToGenerator(): void
    {
        $session = new GameSession();
        $session->setGameMode(GameMode::QCM);
        $session->setDifficulty(GameDifficulty::MEDIUM);
        $session->setTotalQuestions(10);

        $expected = [
            'type' => 'qcm',
            'prompt' => 'Test?',
            'options' => [],
            'correctAnswer' => 'Cumin',
            'baseSpice' => [
                'id' => 1,
                'name' => 'Cannelle',
            ],
            'metadata' => [],
        ];

        $this->generator->expects(self::once())
            ->method('supports')
            ->with(GameMode::QCM)
            ->willReturn(true);
        $this->generator->expects(self::once())
            ->method('generate')
            ->willReturn($expected);

        $manager = $this->makeManager();
        $question = $manager->nextQuestion($session);

        self::assertSame($expected, $question);
    }

    public function testNextQuestionReturnsNullForFinishedSession(): void
    {
        $session = new GameSession();
        $session->setGameMode(GameMode::QCM);
        $session->setDifficulty(GameDifficulty::EASY);
        $session->finish();

        $manager = $this->makeManager();
        self::assertNull($manager->nextQuestion($session));
    }

    public function testAnswerQuestionRecordsCorrectAnswer(): void
    {
        $user = $this->createStub(Users::class);
        $user->method('getId')
            ->willReturn(1);

        $session = new GameSession();
        $session->setUser($user);
        $session->setGameMode(GameMode::QCM);
        $session->setDifficulty(GameDifficulty::EASY);
        $session->setTotalQuestions(10);

        $this->em->method('persist');
        $this->em->method('flush');

        $manager = $this->makeManager();
        $result = $manager->answerQuestion($session, 'Cumin', 'Cumin');

        self::assertTrue($result['correct']);
        self::assertFalse($result['finished']);
        self::assertSame(1, $session->getCorrectAnswers());
    }

    public function testAnswerQuestionRecordsIncorrectAnswer(): void
    {
        $user = $this->createStub(Users::class);
        $user->method('getId')
            ->willReturn(1);

        $session = new GameSession();
        $session->setUser($user);
        $session->setGameMode(GameMode::QCM);
        $session->setDifficulty(GameDifficulty::EASY);
        $session->setTotalQuestions(10);

        $this->em->method('persist');
        $this->em->method('flush');

        $manager = $this->makeManager();
        $result = $manager->answerQuestion($session, 'Poivre', 'Cumin');

        self::assertFalse($result['correct']);
        self::assertSame(0, $session->getCorrectAnswers());
    }

    public function testCalculateXpEasyMode(): void
    {
        $user = $this->createStub(Users::class);
        $user->method('getId')
            ->willReturn(1);

        $session = new GameSession();
        $session->setUser($user);
        $session->setGameMode(GameMode::QCM);
        $session->setDifficulty(GameDifficulty::EASY);
        $session->setTotalQuestions(10);

        // 7 correct * 3 XP * 1.0 multiplier = 21
        for ($i = 0; $i < 7; ++$i) {
            $session->incrementCorrectAnswers();
        }

        $this->sessionRepo->method('countTodayByUser')
            ->willReturn(1);

        $manager = $this->makeManager();
        self::assertSame(21, $manager->calculateXp($session));
    }

    public function testCalculateXpHardModeWithMultiplier(): void
    {
        $user = $this->createStub(Users::class);
        $user->method('getId')
            ->willReturn(1);

        $session = new GameSession();
        $session->setUser($user);
        $session->setGameMode(GameMode::QCM);
        $session->setDifficulty(GameDifficulty::HARD);
        $session->setTotalQuestions(10);

        // 7 correct * 3 XP * 2.0 multiplier = 42
        for ($i = 0; $i < 7; ++$i) {
            $session->incrementCorrectAnswers();
        }

        $this->sessionRepo->method('countTodayByUser')
            ->willReturn(1);

        $manager = $this->makeManager();
        self::assertSame(42, $manager->calculateXp($session));
    }

    public function testCalculateXpReducedAfterThreshold(): void
    {
        $user = $this->createStub(Users::class);
        $user->method('getId')
            ->willReturn(1);

        $session = new GameSession();
        $session->setUser($user);
        $session->setGameMode(GameMode::QCM);
        $session->setDifficulty(GameDifficulty::EASY);
        $session->setTotalQuestions(10);

        // 10 correct * 3 XP * 1.0 * 0.5 (reduced) = 15
        for ($i = 0; $i < 10; ++$i) {
            $session->incrementCorrectAnswers();
        }

        $this->sessionRepo->method('countTodayByUser')
            ->willReturn(4); // > 3 threshold

        $manager = $this->makeManager();
        self::assertSame(15, $manager->calculateXp($session));
    }

    // ── Anti-farming guard on createFinishedSession (Live Component flow) ──

    public function testCreateFinishedSessionThrowsAtDailyLimit(): void
    {
        $user = $this->createStub(Users::class);
        $user->method('getId')
            ->willReturn(1);

        // Mirror what startSession() enforces — LC sessions MUST be capped too.
        $this->sessionRepo->expects(self::once())
            ->method('countTodayByUser')
            ->with($user, GameMode::INTRUS)
            ->willReturn(5);

        // No persist, no dispatch — the throw must happen before.
        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');
        $this->bus->expects(self::never())->method('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Limite quotidienne atteinte/');

        $this->makeManager()
            ->createFinishedSession($user, GameMode::INTRUS, GameDifficulty::EASY, 5, 10, 30);
    }
}
