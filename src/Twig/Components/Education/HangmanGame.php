<?php

declare(strict_types=1);

namespace App\Twig\Components\Education;

use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Service\Education\AcademyManager;
use App\Service\Education\DifficultyRuleApplier;
use App\Service\Education\GameSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class HangmanGame extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $difficulty = 'easy';

    #[LiveProp]
    public string $gameToken = '';

    #[LiveProp]
    public int $questionNumber = 0;

    #[LiveProp]
    public int $totalQuestions = 8;

    #[LiveProp]
    public int $correctCount = 0;

    #[LiveProp]
    public string $maskedWord = '';

    /**
     * @var string[] Normalized uppercase letters
     */
    #[LiveProp]
    public array $guessedLetters = [];

    #[LiveProp]
    public int $errorsCount = 0;

    #[LiveProp]
    public int $maxErrors = 6;

    #[LiveProp]
    public string $hint = '';

    #[LiveProp]
    public bool $wordSolved = false;

    #[LiveProp]
    public bool $wordFailed = false;

    #[LiveProp]
    public string $revealedWord = '';

    #[LiveProp]
    public bool $showFeedback = false;

    #[LiveProp]
    public bool $isFinished = false;

    #[LiveProp]
    public int $startedAt = 0;

    /**
     * Server-side expiration timestamp (anti-cheat).
     */
    #[LiveProp]
    public int $expiresAt = 0;

    /**
     * Time limit in seconds for display.
     */
    #[LiveProp]
    public int $timeLimit = 60;

    #[LiveProp]
    public bool $isTimedOut = false;

    /**
     * @var int[]
     */
    #[LiveProp]
    public array $usedSpiceIds = [];

    public function __construct(
        private readonly AcademyManager $academyManager,
        private readonly GameSessionManager $sessionManager,
        private readonly DifficultyRuleApplier $difficultyRuleApplier,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function mount(string $difficulty = 'easy'): void
    {
        $this->difficulty = $difficulty;
        $this->gameToken = bin2hex(random_bytes(8));
        $this->startedAt = time();

        $gameDifficulty = GameDifficulty::from($this->difficulty);
        $this->maxErrors = $this->academyManager->getHangmanMaxErrors($gameDifficulty);
        $this->timeLimit = $this->difficultyRuleApplier->hangmanTimeLimitSeconds($gameDifficulty);
        $this->expiresAt = time() + $this->timeLimit;

        // Server-side authoritative expiresAt: the LiveProp is mirrored but NEVER trusted on server.
        $this->requestStack->getSession()
            ->set('game_' . $this->gameToken, [
                'expiresAt' => $this->expiresAt,
                'correctCount' => 0,
                'completedWords' => [],
            ]);

        $this->generateWord();
    }

    #[LiveAction]
    public function timeout(): void
    {
        $this->isTimedOut = true;
        $this->isFinished = true;
    }

    #[LiveAction]
    public function guessLetter(#[LiveArg] string $letter): void
    {
        if ($this->wordSolved || $this->wordFailed || $this->isFinished) {
            return;
        }

        $session = $this->requestStack->getSession();
        $secretKey = 'game_' . $this->gameToken;
        $secret = $session->get($secretKey, []);

        // Server-side timer validation — expiresAt is read from session, NOT LiveProp.
        $serverExpiresAt = $secret['expiresAt'] ?? 0;
        if ($serverExpiresAt > 0 && time() > $serverExpiresAt) {
            $this->isTimedOut = true;
            $this->isFinished = true;

            return;
        }

        $normalized = $this->academyManager->normalizeChar($letter);

        // Session-side dedup guard (source of truth — LiveProp is tamperable via replay).
        $serverGuessed = $secret['guessedLetters'] ?? [];

        if (\in_array($normalized, $serverGuessed, true)) {
            return;
        }

        $serverGuessed[] = $normalized;
        $secret['guessedLetters'] = $serverGuessed;
        $session->set($secretKey, $secret);

        $this->guessedLetters[] = $normalized;

        $word = $secret['word'] ?? '';

        if ($word === '') {
            return;
        }

        $found = $this->academyManager->letterInWord($normalized, $word);

        if (! $found) {
            ++$this->errorsCount;
        }

        // Update mask
        $this->maskedWord = $this->academyManager->buildMask($word, $this->guessedLetters);

        // Check win: no underscores left
        if (! str_contains($this->maskedWord, '_')) {
            $this->wordSolved = true;
            $this->revealedWord = $word;
            $this->showFeedback = true;

            $completed = $secret['completedWords'] ?? [];
            if (! \in_array($this->questionNumber, $completed, true)) {
                $completed[] = $this->questionNumber;
                $secret['completedWords'] = $completed;
                $secret['correctCount'] = \count($completed);
                $session->set($secretKey, $secret);
                ++$this->correctCount;
            }
        }

        // Check lose
        if ($this->errorsCount >= $this->maxErrors) {
            $this->wordFailed = true;
            $this->revealedWord = $word;
            $this->showFeedback = true;
        }
    }

    #[LiveAction]
    public function nextWord(): void
    {
        $this->showFeedback = false;
        $this->wordSolved = false;
        $this->wordFailed = false;
        $this->revealedWord = '';
        $this->guessedLetters = [];
        $this->errorsCount = 0;

        if ($this->questionNumber >= $this->totalQuestions) {
            $this->isFinished = true;

            return;
        }

        // Reset timer for the new word — authoritative value stored in session.
        $this->expiresAt = time() + $this->timeLimit;
        $session = $this->requestStack->getSession();
        $secretKey = 'game_' . $this->gameToken;
        $secret = $session->get($secretKey, []);
        $secret['expiresAt'] = $this->expiresAt;
        $session->set($secretKey, $secret);

        $this->generateWord();
    }

    #[LiveAction]
    public function finish(): RedirectResponse
    {
        /** @var Users $user */
        $user = $this->getUser();

        // Authoritative counts from session, never LiveProps.
        $session = $this->requestStack->getSession();
        $secret = $session->get('game_' . $this->gameToken, []);
        $serverCorrect = (int) ($secret['correctCount'] ?? 0);
        $completed = $secret['completedWords'] ?? [];
        $totalWords = max(\count($completed), 1);

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::HANGMAN,
            GameDifficulty::from($this->difficulty),
            $serverCorrect,
            $totalWords,
            $durationSeconds,
        );

        $this->requestStack->getSession()
            ->remove('game_' . $this->gameToken);

        return $this->redirectToRoute('education_result', [
            'id' => $gameSession->getId(),
        ]);
    }

    private function generateWord(): void
    {
        ++$this->questionNumber;
        $gameDifficulty = GameDifficulty::from($this->difficulty);
        $this->maxErrors = $this->academyManager->getHangmanMaxErrors($gameDifficulty);

        $spice = $this->academyManager->pickHangmanSpice($gameDifficulty, $this->usedSpiceIds);

        if ($spice === null) {
            $this->isFinished = true;
            --$this->questionNumber;

            return;
        }

        $this->usedSpiceIds[] = $spice->getId();
        $word = $spice->getName();

        // Build hint by difficulty
        $this->hint = match ($gameDifficulty) {
            GameDifficulty::EASY => sprintf('Famille : %s', $spice->getAromaticGroups()?->getName() ?? '?'),
            GameDifficulty::MEDIUM => sprintf('Type : %s', $spice->getSpicyType()?->getName() ?? '?'),
            GameDifficulty::HARD => '',
        };

        // Pre-reveal common French tool words
        $this->guessedLetters = [];
        $this->maskedWord = $this->academyManager->buildMask($word, $this->guessedLetters);

        // Store secret in session — reset guessedLetters on new word.
        $this->requestStack->getSession()
            ->set('game_' . $this->gameToken, [
                'word' => $word,
                'guessedLetters' => [],
            ]);
    }
}
