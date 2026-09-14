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
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->em->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $work): mixed => $work());
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

    #[DataProvider('xpProvider')]
    public function testCalculateXp(
        GameMode $mode,
        GameDifficulty $difficulty,
        int $correctAnswers,
        int $expected,
    ): void {
        $user = $this->createStub(Users::class);
        $user->method('getId')
            ->willReturn(1);

        $session = new GameSession();
        $session->setUser($user);
        $session->setGameMode($mode);
        $session->setDifficulty($difficulty);
        $session->setTotalQuestions(10);

        for ($i = 0; $i < $correctAnswers; ++$i) {
            $session->incrementCorrectAnswers();
        }

        $manager = $this->makeManager();
        self::assertSame($expected, $manager->calculateXp($session));
    }

    /**
     * @return iterable<string, array{GameMode, GameDifficulty, int, int}>
     */
    public static function xpProvider(): iterable
    {
        yield 'qcm facile sans multiplicateur' => [GameMode::QCM, GameDifficulty::EASY, 7, 21];
        yield 'qcm difficile, multiplicateur x2' => [GameMode::QCM, GameDifficulty::HARD, 7, 42];
        yield 'pendu revalorisé à 8 XP par mot' => [GameMode::HANGMAN, GameDifficulty::EASY, 5, 40];
        yield 'plafond de session atteint' => [
            GameMode::HANGMAN,
            GameDifficulty::HARD,
            5,
            GameSessionManager::MAX_XP_PER_SESSION,
        ];
        yield 'aucune bonne réponse, aucun XP' => [GameMode::QCM, GameDifficulty::HARD, 0, 0];
    }

    public function testCalculateXpNeverQueriesTheDailyCount(): void
    {
        $user = $this->createStub(Users::class);
        $user->method('getId')
            ->willReturn(1);

        $session = new GameSession();
        $session->setUser($user);
        $session->setGameMode(GameMode::QCM);
        $session->setDifficulty(GameDifficulty::EASY);
        $session->setTotalQuestions(10);
        $session->incrementCorrectAnswers();

        $this->sessionRepo->expects(self::never())
            ->method('countTodayByUser');

        self::assertSame(3, $this->makeManager()->calculateXp($session));
    }

    #[DataProvider('gamePointsProvider')]
    public function testConvertGamePoints(GameDifficulty $difficulty, int $points, int $expected): void
    {
        self::assertSame($expected, $this->makeManager()->convertGamePoints($points, $difficulty));
    }

    /**
     * @return iterable<string, array{GameDifficulty, int, int}>
     */
    public static function gamePointsProvider(): iterable
    {
        yield 'points bruts en facile' => [GameDifficulty::EASY, 40, 40];
        yield 'points x1.5 en moyen' => [GameDifficulty::MEDIUM, 30, 45];
        yield 'points x2 en difficile' => [GameDifficulty::HARD, 25, 50];
        yield 'score élevé ramené au plafond' => [
            GameDifficulty::EASY,
            255,
            GameSessionManager::MAX_XP_PER_SESSION,
        ];
        yield 'plafond atteint par le multiplicateur' => [
            GameDifficulty::HARD,
            80,
            GameSessionManager::MAX_XP_PER_SESSION,
        ];
    }

    public function testCreateFinishedSessionThrowsAtDailyLimit(): void
    {
        $user = $this->createStub(Users::class);
        $user->method('getId')
            ->willReturn(1);

        $this->sessionRepo->expects(self::once())
            ->method('countTodayByUser')
            ->with($user, GameMode::INTRUS)
            ->willReturn(5);

        $this->em->expects(self::never())->method('persist');
        $this->em->expects(self::never())->method('flush');
        $this->bus->expects(self::never())->method('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Limite quotidienne atteinte/');

        $this->makeManager()
            ->createFinishedSession($user, GameMode::INTRUS, GameDifficulty::EASY, 5, 10, 30);
    }
}
