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
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class HangmanGame extends AbstractController
{
    use DefaultActionTrait;
    use GameSessionTrait;

    #[LiveProp]
    public string $difficulty = 'easy';

    #[LiveProp]
    public string $gameToken = '';

    #[LiveProp]
    public int $questionNumber = 0;

    #[LiveProp]
    public int $totalQuestions = 7;

    #[LiveProp]
    public int $correctCount = 0;

    #[LiveProp]
    public int $incorrectCount = 0;

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
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function mount(string $difficulty = 'easy'): void
    {
        $this->difficulty = $difficulty;
        $this->gameToken = bin2hex(random_bytes(8));
        $this->startedAt = time();

        $gameDifficulty = GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY;
        $this->maxErrors = $this->academyManager->getHangmanMaxErrors($gameDifficulty);

        $this->writeSecret([
            'correctCount' => 0,
            'completedWords' => [],
            'questions' => [],
        ]);

        $this->generateWord();
    }

    #[LiveAction]
    public function guessLetter(#[LiveArg] string $letter): void
    {
        if ($this->showFeedback || $this->wordSolved || $this->wordFailed || $this->isFinished) {
            return;
        }

        $secret = $this->readSecret();

        $normalized = $this->academyManager->normalizeChar($letter);

        // Session-side dedup guard (source of truth — LiveProp is tamperable via replay).
        $serverGuessed = $secret['guessedLetters'] ?? [];

        if (\in_array($normalized, $serverGuessed, true)) {
            return;
        }

        $serverGuessed[] = $normalized;
        $secret['guessedLetters'] = $serverGuessed;
        $this->writeSecret($secret);

        // Sync LiveProp from session (source of truth) — avoids stale client state
        $this->guessedLetters = $serverGuessed;

        $word = $secret['word'] ?? '';

        if ($word === '') {
            return;
        }

        $found = $this->academyManager->letterInWord($normalized, $word);

        if (! $found) {
            ++$this->errorsCount;
        }

        $this->maskedWord = $this->academyManager->buildMask($word, $serverGuessed);

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
                ++$this->correctCount;

                $questions = $secret['questions'] ?? [];
                $questions[] = [
                    'questionIndex' => $this->questionNumber - 1,
                    'prompt' => $this->hint ?: $this->translator->trans('ui.edu.prompt.hangman_default'),
                    'correctAnswer' => $word,
                    'answerGiven' => $word,
                    'isCorrect' => true,
                ];
                $secret['questions'] = $questions;
                $this->writeSecret($secret);
            }

            return;
        }

        // Check lose
        if ($this->errorsCount >= $this->maxErrors) {
            $this->wordFailed = true;
            ++$this->incorrectCount;
            $this->revealedWord = $word;
            $this->showFeedback = true;

            $questions = $secret['questions'] ?? [];
            $questions[] = [
                'questionIndex' => $this->questionNumber - 1,
                'prompt' => $this->hint ?: $this->translator->trans('ui.edu.prompt.hangman_default'),
                'correctAnswer' => $word,
                'answerGiven' => '—',
                'isCorrect' => false,
            ];
            $secret['questions'] = $questions;
            $this->writeSecret($secret);
        }
    }

    #[LiveAction]
    public function nextWord(): ?RedirectResponse
    {
        $this->showFeedback = false;
        $this->wordSolved = false;
        $this->wordFailed = false;
        $this->revealedWord = '';
        $this->guessedLetters = [];
        $this->errorsCount = 0;

        if ($this->questionNumber >= $this->totalQuestions) {
            return $this->doFinish();
        }

        $this->generateWord();

        if ($this->isFinished) {
            return $this->doFinish();
        }

        return null;
    }

    #[LiveAction]
    public function finish(): RedirectResponse
    {
        return $this->doFinish();
    }

    private function doFinish(): RedirectResponse
    {
        /** @var Users $user */
        $user = $this->getUser();

        $secret = $this->readSecret();

        // Guard: orphan token
        if (empty($secret)) {
            return $this->redirectToRoute('education_index');
        }

        $serverCorrect = (int) ($secret['correctCount'] ?? 0);
        $questionHistory = $secret['questions'] ?? [];

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::HANGMAN,
            GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY,
            $serverCorrect,
            $this->questionNumber,
            $durationSeconds,
        );

        $this->sessionManager->addQuestionsToSession($gameSession, $questionHistory);
        $this->removeSecret();

        return $this->redirectToRoute('education_result', [
            'id' => $gameSession->getId(),
        ]);
    }

    private function generateWord(): void
    {
        ++$this->questionNumber;
        $gameDifficulty = GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY;
        $this->maxErrors = $this->academyManager->getHangmanMaxErrors($gameDifficulty);

        $spice = $this->academyManager->pickHangmanSpice($gameDifficulty, $this->usedSpiceIds);

        if ($spice === null) {
            $this->isFinished = true;
            --$this->questionNumber;

            return;
        }

        $this->usedSpiceIds[] = $spice->getId();
        $word = $spice->getName();

        $this->hint = match ($gameDifficulty) {
            GameDifficulty::EASY => $this->translator->trans('ui.edu.prompt.hangman_family', [
                '%value%' => $spice->getAromaticGroups()?->getName() ?? '?',
            ]),
            GameDifficulty::MEDIUM => $this->translator->trans('ui.edu.prompt.hangman_type', [
                '%value%' => $spice->getSpicyType()?->getName() ?? '?',
            ]),
            GameDifficulty::HARD => '',
        };

        $this->guessedLetters = [];
        $this->maskedWord = $this->academyManager->buildMask($word, $this->guessedLetters);

        // Preserve accumulated session state (correctCount, completedWords, questions).
        $previous = $this->readSecret();
        $this->writeSecret([
            'word' => $word,
            'guessedLetters' => [],
            'correctCount' => $previous['correctCount'] ?? 0,
            'completedWords' => $previous['completedWords'] ?? [],
            'questions' => $previous['questions'] ?? [],
        ]);
    }
}
