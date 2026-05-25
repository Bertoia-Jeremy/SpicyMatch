<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Education;

use App\Service\Education\AcademyManager;
use App\Service\Education\GameSessionManager;
use App\Twig\Components\Education\GuessWhoGame;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Unit tests for GuessWhoGame::revealClue(), guess(), and next().
 *
 * Security focus:
 *  - guess() validates spiceName against server-side whitelist (getAllSpiceNames).
 *  - Replay guard: currentStep already in answeredSteps → rejected.
 *  - Scoring uses clueCount from LiveProp (server-generated — not user-tamperable here).
 */
#[AllowMockObjectsWithoutExpectations]
final class GuessWhoGameTest extends TestCase
{
    private const string TOKEN = 'guesswho_test_tok';

    private AcademyManager&MockObject $academyManager;
    private GameSessionManager&MockObject $sessionManager;

    protected function setUp(): void
    {
        $this->academyManager = $this->createMock(AcademyManager::class);
        $this->sessionManager = $this->createMock(GameSessionManager::class);

        // Default whitelist for guess() spiceName validation
        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                1 => [
                    'id' => 1,
                    'name' => 'Cannelle',
                ],
                2 => [
                    'id' => 2,
                    'name' => 'Cumin',
                ],
                3 => [
                    'id' => 3,
                    'name' => 'Poivre',
                ],
            ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $secret
     *
     * @return array{GuessWhoGame, Session}
     */
    private function makeGame(array $secret = []): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('game_' . self::TOKEN, $secret);

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $game = new GuessWhoGame($this->academyManager, $this->sessionManager, $requestStack);
        $game->gameToken = self::TOKEN;
        $game->questionNumber = 1;
        $game->totalQuestions = 10;

        return [$game, $session];
    }

    /**
     * @param array<array{type: string, label: string, value: string}> $allClues
     *
     * @return array<string, mixed>
     */
    private function baseSecret(string $correctName = 'Cannelle', int $step = 1, array $allClues = []): array
    {
        return [
            'correctName' => $correctName,
            'currentStep' => $step,
            'answeredSteps' => [],
            'correctSteps' => [],
            'totalScore' => 0,
            'questions' => [],
            'allClues' => $allClues,
        ];
    }

    // ── revealClue() ─────────────────────────────────────────────────────────

    public function testRevealClueAppendsNextClueToRevealedClues(): void
    {
        $clues = [
            [
                'type' => 'description',
                'label' => 'Description',
                'value' => 'Épice chaude',
            ],
            [
                'type' => 'alchemyFlavors',
                'label' => 'Saveurs',
                'value' => 'Épicé, Chaud',
            ],
        ];
        [$game] = $this->makeGame($this->baseSecret(allClues: $clues));
        $game->revealedClues = [$clues[0]]; // first clue already shown
        $game->currentClueIndex = 1;

        $game->revealClue();

        self::assertCount(2, $game->revealedClues);
        self::assertSame(2, $game->currentClueIndex);
    }

    public function testRevealClueDoesNothingWhenShowFeedbackIsTrue(): void
    {
        $clues = [
            [
                'type' => 'description',
                'label' => 'Description',
                'value' => 'Épice chaude',
            ],
        ];
        [$game] = $this->makeGame($this->baseSecret(allClues: $clues));
        $game->currentClueIndex = 0;
        $game->showFeedback = true;

        $game->revealClue();

        self::assertCount(0, $game->revealedClues);
    }

    public function testRevealClueDoesNothingWhenIsFinished(): void
    {
        $clues = [
            [
                'type' => 'description',
                'label' => 'Description',
                'value' => 'Épice chaude',
            ],
        ];
        [$game] = $this->makeGame($this->baseSecret(allClues: $clues));
        $game->currentClueIndex = 0;
        $game->isFinished = true;

        $game->revealClue();

        self::assertCount(0, $game->revealedClues);
    }

    public function testRevealClueDoesNothingWhenAllCluesAlreadyRevealed(): void
    {
        $clues = [
            [
                'type' => 'description',
                'label' => 'Description',
                'value' => 'Épice chaude',
            ],
        ];
        [$game] = $this->makeGame($this->baseSecret(allClues: $clues));
        $game->revealedClues = [$clues[0]];
        $game->currentClueIndex = 1; // already past the end

        $game->revealClue();

        self::assertCount(1, $game->revealedClues); // unchanged
    }

    // ── guess() — guards ──────────────────────────────────────────────────────

    public function testGuessDoesNothingWhenShowFeedback(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->showFeedback = true;

        $result = $game->guess('Cannelle');

        self::assertNull($result);
        self::assertSame(0, $game->correctCount);
    }

    /**
     * Security: spiceName not in server-side whitelist must be rejected silently.
     */
    public function testGuessRejectsSpiceNameNotInWhitelist(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));

        $result = $game->guess('EpiceInconnue'); // not in getAllSpiceCards()

        self::assertNull($result);
        self::assertSame(0, $game->correctCount);
        self::assertFalse($game->showFeedback);
    }

    /**
     * Security: each question step can only be answered once.
     */
    public function testGuessReplayGuardBlocksAlreadyAnsweredStep(): void
    {
        $secret = $this->baseSecret('Cannelle', step: 1);
        $secret['answeredSteps'] = [1]; // already answered

        [$game] = $this->makeGame($secret);

        $result = $game->guess('Cannelle');

        self::assertNull($result);
        self::assertFalse($game->showFeedback);
    }

    // ── guess() — correct answer (non-last question) ──────────────────────────

    public function testGuessCorrectAnswerIncrementsCorrectCount(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = [[
            'type' => 'description',
            'label' => 'D',
            'value' => 'v',
        ]]; // 1 clue → 10 points

        $game->guess('Cannelle');

        self::assertSame(1, $game->correctCount);
        self::assertSame(0, $game->incorrectCount);
    }

    public function testGuessCorrectAnswerWith1ClueEarns10Points(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = [[
            'type' => 'description',
            'label' => 'D',
            'value' => 'v',
        ]]; // 1 clue

        $game->guess('Cannelle');

        self::assertSame(10, $game->lastPointsEarned);
        self::assertSame(10, $game->totalScore);
    }

    public function testGuessCorrectAnswerWith2CluesEarns8Points(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = [
            [
                'type' => 'description',
                'label' => 'D',
                'value' => 'v',
            ],
            [
                'type' => 'alchemyFlavors',
                'label' => 'S',
                'value' => 'w',
            ],
        ];

        $game->guess('Cannelle');

        self::assertSame(8, $game->lastPointsEarned);
    }

    public function testGuessCorrectAnswerWith3CluesEarns6Points(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = array_fill(0, 3, [
            'type' => 't',
            'label' => 'L',
            'value' => 'v',
        ]);

        $game->guess('Cannelle');

        self::assertSame(6, $game->lastPointsEarned);
    }

    public function testGuessCorrectAnswerWith5OrMoreCluesEarns2Points(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = array_fill(0, 5, [
            'type' => 't',
            'label' => 'L',
            'value' => 'v',
        ]);

        $game->guess('Cannelle');

        self::assertSame(2, $game->lastPointsEarned);
    }

    public function testGuessCorrectAnswerShowsFeedback(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = [[
            'type' => 'description',
            'label' => 'D',
            'value' => 'v',
        ]];

        $game->guess('Cannelle');

        self::assertTrue($game->lastAnswerCorrect);
        self::assertSame('Cannelle', $game->lastCorrectName);
        self::assertTrue($game->showFeedback);
    }

    // ── guess() — wrong answer ────────────────────────────────────────────────

    public function testGuessWrongAnswerIncrementsIncorrectCount(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));

        $game->guess('Cumin'); // wrong

        self::assertSame(0, $game->correctCount);
        self::assertSame(1, $game->incorrectCount);
    }

    public function testGuessWrongAnswerShowsFeedbackWithCorrectName(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));

        $game->guess('Cumin');

        self::assertFalse($game->lastAnswerCorrect);
        self::assertSame('Cannelle', $game->lastCorrectName);
        self::assertTrue($game->showFeedback);
    }

    public function testGuessWrongAnswerEarnsZeroPoints(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));

        $game->guess('Cumin');

        self::assertSame(0, $game->lastPointsEarned);
        self::assertSame(0, $game->totalScore);
    }

    // ── guess() — session persistence ────────────────────────────────────────

    public function testGuessStoresAnsweredStepInSession(): void
    {
        [$game, $session] = $this->makeGame($this->baseSecret('Cannelle', step: 2));
        $game->questionNumber = 2;
        $game->revealedClues = [[
            'type' => 't',
            'label' => 'L',
            'value' => 'v',
        ]];

        $game->guess('Cannelle');

        $stored = $session->get('game_' . self::TOKEN);
        self::assertContains(2, $stored['answeredSteps']);
    }

    public function testGuessStoresScoreInSession(): void
    {
        [$game, $session] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = [[
            'type' => 't',
            'label' => 'L',
            'value' => 'v',
        ]]; // 10 pts

        $game->guess('Cannelle');

        $stored = $session->get('game_' . self::TOKEN);
        self::assertSame(10, $stored['totalScore']);
    }

    // ── next() ────────────────────────────────────────────────────────────────

    public function testNextResetsFeedbackState(): void
    {
        // generateQuestion() → getAllSpiceCards() → mock returns 3 cards but all get excluded
        // or generateQuestion falls through with isFinished=true → no AbstractController call.
        [$game] = $this->makeGame([
            'answeredSteps' => [],
            'correctSteps' => [],
            'totalScore' => 0,
            'questions' => [],
        ]);
        $game->showFeedback = true;
        $game->lastAnswerCorrect = true;
        $game->lastPointsEarned = 8;
        $game->lastCorrectName = 'Cannelle';
        $game->questionNumber = 2;
        $game->totalQuestions = 10;

        // getAllSpiceCards is already stubbed in setUp() — 3 cards returned
        // generateQuestion() picks a card; AcademyManager::generateGuessWhoClues is mocked → []
        // With no clues: totalCluesCount=0, but revealedClues=[allClues[0]] would be out of bounds.
        // The LC calls: $allClues = generateGuessWhoClues(); $this->totalCluesCount = count($allClues);
        // $this->currentClueIndex = 1; $this->revealedClues = [$allClues[0]]; — ← would throw if empty.
        // So mock returns at least 1 clue.
        $this->academyManager->method('generateGuessWhoClues')
            ->willReturn([[
                'type' => 't',
                'label' => 'L',
                'value' => 'v',
            ]]);
        $this->academyManager->method('countAvailableClues')
            ->willReturn(4);

        $game->next();

        self::assertFalse($game->showFeedback);
        self::assertNull($game->lastAnswerCorrect);
        self::assertSame(0, $game->lastPointsEarned);
        self::assertSame('', $game->lastCorrectName);
    }
}
