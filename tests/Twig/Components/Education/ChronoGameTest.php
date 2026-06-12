<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Education;

use App\Service\Education\AcademyManager;
use App\Service\Education\GameSessionManager;
use App\Twig\Components\Education\ChronoGame;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Unit tests for ChronoGame::answer() and getCurrentCard().
 *
 * answer() is only tested for the non-finish path (time not elapsed).
 * finish() / timeout() call AbstractController::getUser() → integration only.
 *
 * Security focus:
 *  - Server-side elapsed-time check prevents answering after timeout.
 *  - Score/streak computed server-side from session, not from LiveProps.
 *  - wrongAnswerCooldown blocks rapid-fire wrong answers (anti-farming).
 */
#[AllowMockObjectsWithoutExpectations]
final class ChronoGameTest extends TestCase
{
    private const string TOKEN = 'chrono_test_tok';

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
     * @return array{ChronoGame, Session}
     */
    private function makeGame(array $secret = []): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('game_' . self::TOKEN, $secret);

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $game = new ChronoGame($this->academyManager, $this->sessionManager, $requestStack);
        $game->gameToken = self::TOKEN;
        $game->difficulty = 'easy';
        $game->timeLimit = 90;
        $game->startedAt = time();

        return [$game, $session];
    }

    /**
     * Base session secret for an in-progress question.
     *
     * @return array<string, mixed>
     */
    private function inProgressSecret(string $correctName = 'Cannelle', int $timeOffset = 0): array
    {
        return [
            'correctName' => $correctName,
            'questionStartedAt' => time() - $timeOffset,
            'correctCount' => 0,
            'questionsAnswered' => 0,
            'totalScore' => 0,
            'streak' => 0,
            'startedAt' => time(),
            'timeLimit' => 90,
        ];
    }

    // ── getCurrentCard() ─────────────────────────────────────────────────────

    public function testGetCurrentCardReturnsEmptyWhenCurrentCardIdIsZero(): void
    {
        [$game] = $this->makeGame();
        $game->currentCardId = 0;

        $card = $game->getCurrentCard();

        self::assertSame([], $card);
    }

    public function testGetCurrentCardReturnsEmptyWhenCardNotFound(): void
    {
        [$game] = $this->makeGame();
        $game->currentCardId = 999;
        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([]);

        $card = $game->getCurrentCard();

        self::assertSame([], $card);
    }

    public function testGetCurrentCardEasyDifficultyReturnsAllFields(): void
    {
        [$game] = $this->makeGame();
        $game->currentCardId = 1;
        $game->difficulty = 'easy';

        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                1 => [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'file' => 'cannelle.jpg',
                    'description' => 'Épice chaude',
                    'aromaticGroup' => [
                        'name' => 'Aromatiques',
                    ],
                    'spicyType' => 'Herbacé',
                    'mainCompounds' => ['Cinnamaldéhyde'],
                    'secondaryCompounds' => [],
                    'alchemyFlavors' => ['Chaud'],
                    'cookingTips' => [],
                ],
            ]);

        $card = $game->getCurrentCard();

        self::assertArrayHasKey('file', $card);
        self::assertArrayHasKey('description', $card);
        self::assertArrayHasKey('mainCompounds', $card);
        self::assertArrayNotHasKey('id', $card);   // name/id intentionally hidden
        self::assertArrayNotHasKey('name', $card);
    }

    public function testGetCurrentCardHardDifficultyReturnsOnlyCompoundsAndFlavors(): void
    {
        [$game] = $this->makeGame();
        $game->currentCardId = 1;
        $game->difficulty = 'hard';

        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                1 => [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'file' => 'cannelle.jpg',
                    'description' => 'Épice chaude',
                    'aromaticGroup' => [
                        'name' => 'Aromatiques',
                    ],
                    'spicyType' => 'Herbacé',
                    'mainCompounds' => ['Cinnamaldéhyde'],
                    'secondaryCompounds' => [],
                    'alchemyFlavors' => ['Chaud'],
                    'cookingTips' => [],
                ],
            ]);

        $card = $game->getCurrentCard();

        self::assertArrayHasKey('mainCompounds', $card);
        self::assertArrayHasKey('alchemyFlavors', $card);
        self::assertArrayNotHasKey('file', $card);
        self::assertArrayNotHasKey('description', $card);
        self::assertArrayNotHasKey('aromaticGroup', $card);
    }

    public function testGetCurrentCardIsMemoizedOnSecondCall(): void
    {
        [$game] = $this->makeGame();
        $game->currentCardId = 1;

        $this->academyManager->expects(self::once()) // called exactly once despite two invocations
            ->method('getAllSpiceCards')
            ->willReturn([
                1 => [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'file' => null,
                    'description' => '',
                    'aromaticGroup' => [],
                    'spicyType' => '',
                    'mainCompounds' => [],
                    'secondaryCompounds' => [],
                    'alchemyFlavors' => [],
                    'cookingTips' => [],
                ],
            ]);

        $game->getCurrentCard();
        $game->getCurrentCard();
    }

    // ── answer() — guards ─────────────────────────────────────────────────────

    public function testAnswerDoesNothingWhenIsFinished(): void
    {
        [$game] = $this->makeGame($this->inProgressSecret());
        $game->isFinished = true;

        $result = $game->answer('Cannelle');

        self::assertNull($result);
        self::assertSame(0, $game->correctCount);
    }

    public function testAnswerReturnsNullWhenQuestionStartedAtIsNull(): void
    {
        $secret = $this->inProgressSecret();
        $secret['questionStartedAt'] = null;

        [$game] = $this->makeGame($secret);

        $result = $game->answer('Cannelle');

        self::assertNull($result);
        self::assertSame(0, $game->correctCount);
    }

    /**
     * Security: anti-farming cooldown — wrong answers cannot be spammed.
     */
    public function testAnswerReturnsNullDuringWrongAnswerCooldown(): void
    {
        $secret = $this->inProgressSecret();
        $secret['wrongAnswerCooldown'] = time() + 10; // cooldown active

        [$game] = $this->makeGame($secret);

        // generateQuestion() would be called if we proceed — but with cooldown active we return early
        $result = $game->answer('Cannelle');

        self::assertNull($result);
        self::assertTrue($game->isInCooldown);
    }

    // ── answer() — correct answer ─────────────────────────────────────────────

    public function testAnswerCorrectIncrementsCorrectCountAndStreak(): void
    {
        [$game] = $this->makeGame($this->inProgressSecret('Cannelle'));

        // generateQuestion() will be called after a correct answer → stub it
        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null); // → isFinished=true
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cannelle'); // correct

        self::assertSame(1, $game->correctCount);
        self::assertSame(1, $game->streak);
    }

    public function testAnswerCorrectUpdatesLastAnswerCorrectAndName(): void
    {
        [$game] = $this->makeGame($this->inProgressSecret('Cannelle'));

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cannelle');

        self::assertTrue($game->lastAnswerCorrect);
        self::assertSame('Cannelle', $game->lastCorrectName);
    }

    public function testAnswerCorrectEarnsAtLeastOnePoint(): void
    {
        [$game] = $this->makeGame($this->inProgressSecret('Cannelle', timeOffset: 30));
        // timeOffset=30s → base=1 (default), streak bonus=0 → 1 point

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cannelle');

        self::assertGreaterThanOrEqual(1, $game->lastPointsEarned);
    }

    // ── answer() — wrong answer ───────────────────────────────────────────────

    public function testAnswerWrongResetsStreak(): void
    {
        $secret = $this->inProgressSecret('Cannelle');
        $secret['streak'] = 3;

        [$game] = $this->makeGame($secret);
        $game->streak = 3;

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cumin'); // wrong

        self::assertSame(0, $game->streak);
        self::assertFalse($game->lastAnswerCorrect);
    }

    public function testAnswerWrongSetsCooldownInSession(): void
    {
        [$game, $session] = $this->makeGame($this->inProgressSecret('Cannelle'));

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cumin');

        $stored = $session->get('game_' . self::TOKEN);
        self::assertArrayHasKey('wrongAnswerCooldown', $stored);
        self::assertGreaterThan(time(), $stored['wrongAnswerCooldown']);
    }

    public function testAnswerWrongEarnsZeroPoints(): void
    {
        [$game] = $this->makeGame($this->inProgressSecret('Cannelle'));

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cumin');

        self::assertSame(0, $game->lastPointsEarned);
    }

    // ── answer() — difficulty-based timing ────────────────────────────────────

    public function testAnswerCorrectFastResponseEarnsHighBasePoints(): void
    {
        // EASY: [t1=8, t2=12]. questionStartedAt = 2 seconds ago → elapsed < 8 → base=5
        $secret = $this->inProgressSecret('Cannelle', timeOffset: 2);

        [$game] = $this->makeGame($secret);
        $game->difficulty = 'easy';

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cannelle');

        // base=5, streak=1 (first correct) → streakBonus=min(max(1-1,0),3)=0 → 5
        self::assertSame(5, $game->lastPointsEarned);
    }

    public function testAnswerCorrectStreakBonusMaxesAt3(): void
    {
        // streak=4 → streakBonus = min(max(4-1,0),3) = min(3,3) = 3
        $secret = $this->inProgressSecret('Cannelle', timeOffset: 1);
        $secret['streak'] = 4;

        [$game] = $this->makeGame($secret);
        $game->difficulty = 'easy';
        $game->streak = 4;

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cannelle');

        // base=5, streakBonus=3 → 8
        self::assertSame(8, $game->lastPointsEarned);
    }
}
