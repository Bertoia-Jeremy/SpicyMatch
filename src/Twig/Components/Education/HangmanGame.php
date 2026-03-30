<?php

declare(strict_types=1);

namespace App\Twig\Components\Education;

use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Service\Education\AcademyManager;
use App\Service\Education\GameSessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
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
     * @var int[]
     */
    #[LiveProp]
    public array $usedSpiceIds = [];

    public function __construct(
        private readonly AcademyManager $academyManager,
        private readonly GameSessionManager $sessionManager,
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

        $this->generateWord();
    }

    #[LiveAction]
    public function guessLetter(string $letter): void
    {
        if ($this->wordSolved || $this->wordFailed || $this->isFinished) {
            return;
        }

        $normalized = $this->academyManager->normalizeChar($letter);

        if (in_array($normalized, $this->guessedLetters, true)) {
            return;
        }

        $this->guessedLetters[] = $normalized;

        // Get the actual word from session
        $session = $this->requestStack->getSession();
        $secret = $session->get('game_' . $this->gameToken, []);
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
            ++$this->correctCount;
            $this->revealedWord = $word;
            $this->showFeedback = true;
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

        $this->generateWord();
    }

    #[LiveAction]
    public function finish(): RedirectResponse
    {
        /** @var Users $user */
        $user = $this->getUser();

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::HANGMAN,
            GameDifficulty::from($this->difficulty),
            $this->correctCount,
            $this->questionNumber,
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

        // Store secret in session
        $this->requestStack->getSession()
            ->set('game_' . $this->gameToken, [
                'word' => $word,
            ]);
    }
}
