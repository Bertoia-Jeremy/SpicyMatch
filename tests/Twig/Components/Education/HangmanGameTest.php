<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Education;

use App\Service\Education\AcademyManager;
use App\Service\Education\GameSessionManager;
use App\Twig\Components\Education\HangmanGame;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Unit tests for HangmanGame::guessLetter().
 *
 * Uses a REAL AcademyManager (with mocked repos + ArrayAdapter) so that the
 * normalizeChar / letterInWord / buildMask logic is genuinely exercised,
 * not just proxied through a mock.
 *
 * Security focus:
 *  - Session-side dedup guard prevents re-processing duplicate letters.
 *  - LiveProp guessedLetters is only updated from session (not from client value).
 */
#[AllowMockObjectsWithoutExpectations]
final class HangmanGameTest extends TestCase
{
    private const string TOKEN = 'hangman_test_tok';

    private AcademyManager $academyManager;
    private GameSessionManager&MockObject $sessionManager;

    protected function setUp(): void
    {
        // Real AcademyManager — mocked repos won't be called by guessLetter()
        $this->academyManager = new AcademyManager(
            $this->createMock(\App\Repository\SpicesRepository::class),
            $this->createMock(\App\Service\Match\CompatibleSpiceFinder::class),
            new ArrayAdapter(),
            new IdentityTranslator(),
        );
        $this->sessionManager = $this->createMock(GameSessionManager::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $secret
     *
     * @return array{HangmanGame, Session}
     */
    private function makeGame(array $secret = []): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('game_'.self::TOKEN, $secret);

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $game = new HangmanGame($this->academyManager, $this->sessionManager, $requestStack, new IdentityTranslator());
        $game->gameToken = self::TOKEN;
        $game->questionNumber = 1;
        $game->totalQuestions = 7;
        $game->maxErrors = 6;

        return [$game, $session];
    }

    /**
     * @return array<string, mixed>
     */
    private function wordSecret(string $word): array
    {
        return [
            'word' => $word,
            'guessedLetters' => [],
            'correctCount' => 0,
            'completedWords' => [],
            'questions' => [],
        ];
    }

    // ── guessLetter() — guards ────────────────────────────────────────────────

    public function testGuessLetterDoesNothingWhenShowFeedbackIsTrue(): void
    {
        [$game] = $this->makeGame($this->wordSecret('Thym'));
        $game->showFeedback = true;

        $game->guessLetter('T');

        self::assertSame([], $game->guessedLetters);
        self::assertSame(0, $game->errorsCount);
    }

    public function testGuessLetterDoesNothingWhenWordSolved(): void
    {
        [$game] = $this->makeGame($this->wordSecret('Thym'));
        $game->wordSolved = true;

        $game->guessLetter('T');

        self::assertSame([], $game->guessedLetters);
    }

    public function testGuessLetterDoesNothingWhenWordFailed(): void
    {
        [$game] = $this->makeGame($this->wordSecret('Thym'));
        $game->wordFailed = true;

        $game->guessLetter('T');

        self::assertSame([], $game->guessedLetters);
    }

    public function testGuessLetterDoesNothingWhenIsFinished(): void
    {
        [$game] = $this->makeGame($this->wordSecret('Thym'));
        $game->isFinished = true;

        $game->guessLetter('T');

        self::assertSame([], $game->guessedLetters);
    }

    /**
     * Security: session-side dedup — replaying the same letter must be a no-op.
     *
     * The guard returns early, so the LiveProp is not updated and errorsCount
     * remains unchanged. The session must still contain only one 'T' (not two).
     */
    public function testGuessLetterSessionDedupGuardBlocksDuplicateLetter(): void
    {
        $secret = $this->wordSecret('Thym');
        $secret['guessedLetters'] = ['T']; // already guessed on server

        [$game, $session] = $this->makeGame($secret);

        $game->guessLetter('T'); // duplicate — early return before LiveProp update

        // errorsCount must not change (the duplicate is not an error)
        self::assertSame(0, $game->errorsCount);

        // Session must still have exactly ['T'], not ['T', 'T']
        $stored = $session->get('game_'.self::TOKEN);
        self::assertSame(['T'], $stored['guessedLetters']);
    }

    // ── guessLetter() — found letter ─────────────────────────────────────────

    public function testGuessLetterFoundDoesNotIncrementErrors(): void
    {
        [$game] = $this->makeGame($this->wordSecret('Thym'));

        $game->guessLetter('T');

        self::assertSame(0, $game->errorsCount);
    }

    public function testGuessLetterFoundAddsNormalizedLetterToSession(): void
    {
        [$game, $session] = $this->makeGame($this->wordSecret('Thym'));

        $game->guessLetter('t'); // lowercase — normalized to 'T'

        $stored = $session->get('game_'.self::TOKEN);
        self::assertContains('T', $stored['guessedLetters']);
    }

    public function testGuessLetterFoundUpdatesMaskedWord(): void
    {
        [$game] = $this->makeGame($this->wordSecret('Thym'));
        $game->maskedWord = '____';

        $game->guessLetter('T');

        self::assertSame('T___', $game->maskedWord);
    }

    public function testGuessLetterAccentInsensitive(): void
    {
        // Guessing 'E' should reveal 'É' in 'Épice'
        [$game] = $this->makeGame($this->wordSecret('Épice'));
        $game->maskedWord = '_____';

        $game->guessLetter('E');

        // É normalized to E → revealed, p/i/c still hidden, e also revealed
        self::assertStringContainsString('É', $game->maskedWord);
    }

    // ── guessLetter() — not found (error) ────────────────────────────────────

    public function testGuessLetterNotFoundIncrementsErrors(): void
    {
        [$game] = $this->makeGame($this->wordSecret('Thym'));

        $game->guessLetter('Z'); // not in 'Thym'

        self::assertSame(1, $game->errorsCount);
    }

    // ── guessLetter() — word solved ───────────────────────────────────────────

    public function testGuessLetterSolvesWordWhenNoUnderscoresLeft(): void
    {
        // Pre-guess T, H, Y — one letter left: M
        $secret = $this->wordSecret('Thym');
        $secret['guessedLetters'] = ['T', 'H', 'Y'];

        [$game] = $this->makeGame($secret);
        $game->maskedWord = 'Thy_';
        $game->guessedLetters = ['T', 'H', 'Y'];

        $game->guessLetter('M');

        self::assertTrue($game->wordSolved);
        self::assertSame('Thym', $game->revealedWord);
        self::assertTrue($game->showFeedback);
        self::assertSame(1, $game->correctCount);
    }

    // ── guessLetter() — word failed ───────────────────────────────────────────

    public function testGuessLetterSetsWordFailedWhenMaxErrorsReached(): void
    {
        [$game] = $this->makeGame($this->wordSecret('Thym'));
        $game->maxErrors = 1; // already at limit
        $game->errorsCount = 0;

        $game->guessLetter('Z'); // wrong → errorsCount = 1 ≥ maxErrors = 1

        self::assertTrue($game->wordFailed);
        self::assertSame(1, $game->incorrectCount);
        self::assertSame('Thym', $game->revealedWord);
        self::assertTrue($game->showFeedback);
    }

    public function testGuessLetterFailedWordStoresQuestionHistoryInSession(): void
    {
        [$game, $session] = $this->makeGame($this->wordSecret('Thym'));
        $game->maxErrors = 1;
        $game->hint = 'Famille : Apiaceae';

        $game->guessLetter('Z');

        $stored = $session->get('game_'.self::TOKEN);
        self::assertCount(1, $stored['questions']);
        self::assertFalse($stored['questions'][0]['isCorrect']);
        self::assertSame('Thym', $stored['questions'][0]['correctAnswer']);
    }
}
