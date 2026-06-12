<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Education;

use App\Service\Education\AcademyManager;
use App\Service\Education\DifficultyRuleApplier;
use App\Service\Education\GameSessionManager;
use App\Twig\Components\Education\IntrusGame;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Unit tests for IntrusGame::answer() and IntrusGame::next().
 *
 * Only methods that do NOT call AbstractController::getUser() / redirectToRoute()
 * are covered here. The doFinish() path requires a kernel (integration suite).
 *
 * Security focus: replay guards, session-side truth, idempotency.
 */
#[AllowMockObjectsWithoutExpectations]
final class IntrusGameTest extends TestCase
{
    private const string TOKEN = 'intrus_test_tok';

    private AcademyManager&MockObject $academyManager;
    private GameSessionManager&MockObject $sessionManager;

    protected function setUp(): void
    {
        $this->academyManager = $this->createMock(AcademyManager::class);
        $this->sessionManager = $this->createMock(GameSessionManager::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $secret
     *
     * @return array{IntrusGame, Session}
     */
    private function makeGame(array $secret = []): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('game_' . self::TOKEN, $secret);

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $game = new IntrusGame(
            $this->academyManager,
            $this->sessionManager,
            new DifficultyRuleApplier(), // final class — use real instance
            $requestStack,
        );
        $game->gameToken = self::TOKEN;
        $game->questionNumber = 1;
        $game->totalQuestions = 10;

        return [$game, $session];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSecret(int $correctId = 42, int $step = 1): array
    {
        return [
            'correctAnswerId' => $correctId,
            'currentStep' => $step,
            'answeredSteps' => [],
            'correctSteps' => [],
            'questions' => [],
        ];
    }

    private function setOptions(IntrusGame $game, int $correctId = 42, int $otherId = 99): void
    {
        $game->options = [
            [
                'id' => $correctId,
                'name' => 'CorrecteÉpice',
                'file' => null,
                'color' => null,
            ],
            [
                'id' => $otherId,
                'name' => 'MauvaiseÉpice',
                'file' => null,
                'color' => null,
            ],
        ];
        $game->prompt = 'Quel est l\'intrus ?';
    }

    // ── answer() — idempotency guards ────────────────────────────────────────

    public function testAnswerDoesNothingWhenShowFeedbackIsTrue(): void
    {
        [$game] = $this->makeGame($this->baseSecret());
        $this->setOptions($game);
        $game->showFeedback = true;

        $game->answer(42);

        self::assertSame(0, $game->correctCount);
        self::assertSame(0, $game->incorrectCount);
    }

    public function testAnswerDoesNothingWhenIsFinished(): void
    {
        [$game] = $this->makeGame($this->baseSecret());
        $this->setOptions($game);
        $game->isFinished = true;

        $game->answer(42);

        self::assertSame(0, $game->correctCount);
    }

    /**
     * Security: replay attack — same step answered twice must be rejected.
     */
    public function testAnswerReplayGuardBlocksAlreadyAnsweredStep(): void
    {
        $secret = $this->baseSecret(correctId: 42, step: 1);
        $secret['answeredSteps'] = [1]; // step 1 already consumed

        [$game] = $this->makeGame($secret);
        $this->setOptions($game);

        $game->answer(42);

        self::assertSame(0, $game->correctCount);
        self::assertFalse($game->showFeedback);
    }

    // ── answer() — correct pick ───────────────────────────────────────────────

    public function testAnswerCorrectPickIncrementsCorrectCount(): void
    {
        [$game] = $this->makeGame($this->baseSecret(correctId: 42));
        $this->setOptions($game, correctId: 42);

        $game->answer(42);

        self::assertSame(1, $game->correctCount);
        self::assertSame(0, $game->incorrectCount);
    }

    public function testAnswerCorrectPickSetsShowFeedbackAndLastAnswerCorrect(): void
    {
        [$game] = $this->makeGame($this->baseSecret(correctId: 42));
        $this->setOptions($game, correctId: 42);

        $game->answer(42);

        self::assertTrue($game->showFeedback);
        self::assertTrue($game->lastAnswerCorrect);
    }

    public function testAnswerCorrectPickPopulatesLastCorrectAnswerName(): void
    {
        [$game] = $this->makeGame($this->baseSecret(correctId: 42));
        $this->setOptions($game, correctId: 42);

        $game->answer(42);

        self::assertSame('CorrecteÉpice', $game->lastCorrectAnswerName);
    }

    public function testAnswerCorrectPickSetsLastSelectedId(): void
    {
        [$game] = $this->makeGame($this->baseSecret(correctId: 42));
        $this->setOptions($game, correctId: 42);

        $game->answer(42);

        self::assertSame(42, $game->lastSelectedId);
    }

    // ── answer() — wrong pick ─────────────────────────────────────────────────

    public function testAnswerWrongPickIncrementsIncorrectCount(): void
    {
        [$game] = $this->makeGame($this->baseSecret(correctId: 42));
        $this->setOptions($game, correctId: 42, otherId: 99);

        $game->answer(99);

        self::assertSame(0, $game->correctCount);
        self::assertSame(1, $game->incorrectCount);
    }

    public function testAnswerWrongPickSetsLastAnswerCorrectFalse(): void
    {
        [$game] = $this->makeGame($this->baseSecret(correctId: 42));
        $this->setOptions($game, correctId: 42, otherId: 99);

        $game->answer(99);

        self::assertFalse($game->lastAnswerCorrect);
        self::assertTrue($game->showFeedback);
    }

    // ── answer() — session persistence ───────────────────────────────────────

    public function testAnswerPersistsAnsweredStepInSession(): void
    {
        [$game, $session] = $this->makeGame($this->baseSecret(step: 3));
        $this->setOptions($game);
        $game->questionNumber = 3;

        $game->answer(42);

        $stored = $session->get('game_' . self::TOKEN);
        self::assertContains(3, $stored['answeredSteps']);
    }

    public function testAnswerCorrectPickPersistsCorrectStepInSession(): void
    {
        [$game, $session] = $this->makeGame($this->baseSecret(correctId: 42, step: 1));
        $this->setOptions($game, correctId: 42);

        $game->answer(42);

        $stored = $session->get('game_' . self::TOKEN);
        self::assertContains(1, $stored['correctSteps']);
    }

    public function testAnswerAddsQuestionToSessionHistory(): void
    {
        [$game, $session] = $this->makeGame($this->baseSecret(correctId: 42));
        $this->setOptions($game, correctId: 42);

        $game->answer(42);

        $stored = $session->get('game_' . self::TOKEN);
        self::assertCount(1, $stored['questions']);
        self::assertTrue($stored['questions'][0]['isCorrect']);
    }

    // ── next() ───────────────────────────────────────────────────────────────

    public function testNextResetsFeedbackState(): void
    {
        // generateQuestion() → generateIntrusQuestion() → null (mock default) → isFinished=true
        // No AbstractController call → safe.
        [$game] = $this->makeGame([
            'answeredSteps' => [],
            'correctSteps' => [],
            'questions' => [],
        ]);
        $game->showFeedback = true;
        $game->lastAnswerCorrect = true;
        $game->lastCorrectAnswerName = 'Cumin';
        $game->lastSelectedId = 7;
        $game->questionNumber = 2;
        $game->totalQuestions = 10;

        $game->next();

        self::assertFalse($game->showFeedback);
        self::assertNull($game->lastAnswerCorrect);
        self::assertSame('', $game->lastCorrectAnswerName);
        self::assertSame(0, $game->lastSelectedId);
    }
}
