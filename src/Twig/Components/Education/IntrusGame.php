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
class IntrusGame extends AbstractController
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
    public string $baseSpiceName = '';

    #[LiveProp]
    public bool $isInverted = false;

    #[LiveProp]
    public string $prompt = '';

    /**
     * @var array<array{id: int, name: string, file: ?string, color: ?string}>
     */
    #[LiveProp]
    public array $options = [];

    #[LiveProp]
    public bool $showFeedback = false;

    #[LiveProp]
    public ?bool $lastAnswerCorrect = null;

    #[LiveProp]
    public string $lastCorrectAnswerName = '';

    /**
     * ID of the option the user clicked — used to highlight red in feedback mode.
     */
    #[LiveProp]
    public int $lastSelectedId = 0;

    #[LiveProp]
    public bool $isFinished = false;

    #[LiveProp]
    public int $startedAt = 0;

    /**
     * @var array<array{correct: bool}>
     */
    #[LiveProp]
    public array $answers = [];

    /**
     * @var int[] Already used base spice IDs
     */
    #[LiveProp]
    public array $usedBaseIds = [];

    #[LiveProp]
    public string $familyName = '';

    #[LiveProp]
    public string $familyColor = '';

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
        $this->generateQuestion();
    }

    #[LiveAction]
    public function answer(#[LiveArg] int $spiceId): void
    {
        if ($this->showFeedback || $this->isFinished) {
            return;
        }

        $secret = $this->readSecret();
        $correctId = $secret['correctAnswerId'] ?? null;
        $currentStep = $secret['currentStep'] ?? null;
        $answeredSteps = $secret['answeredSteps'] ?? [];
        $correctSteps = $secret['correctSteps'] ?? [];
        $questions = $secret['questions'] ?? [];

        // Replay guard: each question (identified by step) can only be answered once.
        if ($currentStep === null || \in_array($currentStep, $answeredSteps, true)) {
            return;
        }

        $answeredSteps[] = $currentStep;

        $isCorrect = $spiceId === $correctId;
        if ($isCorrect) {
            $correctSteps[] = $currentStep;
        }

        // Find names for feedback display and history
        $correctName = '';
        $selectedName = '';

        foreach ($this->options as $opt) {
            if ($opt['id'] === $correctId) {
                $correctName = $opt['name'];
            }

            if ($opt['id'] === $spiceId) {
                $selectedName = $opt['name'];
            }
        }

        // Store per-question data for answer history on result page
        $questions[] = [
            'questionIndex' => $this->questionNumber - 1,
            'prompt' => $this->prompt,
            'correctAnswer' => $correctName,
            'answerGiven' => $selectedName,
            'isCorrect' => $isCorrect,
        ];

        $secret['answeredSteps'] = $answeredSteps;
        $secret['correctSteps'] = $correctSteps;
        $secret['questions'] = $questions;
        $this->writeSecret($secret);

        // Mirror to LiveProp for UI only — server-side source of truth is the session.
        if ($isCorrect) {
            ++$this->correctCount;
        } else {
            ++$this->incorrectCount;
        }

        $this->answers[] = [
            'correct' => $isCorrect,
        ];
        $this->lastAnswerCorrect = $isCorrect;
        $this->lastCorrectAnswerName = $correctName;
        $this->lastSelectedId = $spiceId;
        $this->showFeedback = true;
    }

    #[LiveAction]
    public function next(): ?RedirectResponse
    {
        $this->showFeedback = false;
        $this->lastAnswerCorrect = null;
        $this->lastCorrectAnswerName = '';
        $this->lastSelectedId = 0;

        if ($this->questionNumber >= $this->totalQuestions) {
            return $this->doFinish();
        }

        $this->generateQuestion();

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

        // Authoritative counts come from session — NOT from LiveProps (client-tamperable).
        $secret = $this->readSecret();
        $answeredSteps = $secret['answeredSteps'] ?? [];
        $correctSteps = $secret['correctSteps'] ?? [];
        $questionHistory = $secret['questions'] ?? [];

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::INTRUS,
            GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY,
            \count($correctSteps),
            \count($answeredSteps),
            $durationSeconds,
        );

        // Persist per-question history so result page can display it
        $this->sessionManager->addQuestionsToSession($gameSession, $questionHistory);

        // Cleanup session secrets
        $this->removeSecret();

        return $this->redirectToRoute('education_result', [
            'id' => $gameSession->getId(),
        ]);
    }

    private function generateQuestion(): void
    {
        ++$this->questionNumber;
        $gameDifficulty = GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY;

        // Alternate between classic and inverted randomly
        $inverted = random_int(0, 1) === 1;

        $strict = $this->difficultyRuleApplier->intrusStrictMode($gameDifficulty);
        $question = $this->academyManager->generateIntrusQuestion(
            $gameDifficulty,
            $this->usedBaseIds,
            $inverted,
            $strict
        );

        if ($question === null) {
            // Not enough data to generate more questions — finish early
            $this->isFinished = true;
            --$this->questionNumber;

            return;
        }

        $this->usedBaseIds[] = $question['baseSpice']['id'];
        $this->baseSpiceName = $question['baseSpice']['name'];
        $this->isInverted = $question['isInverted'];
        $this->prompt = $question['prompt'];
        $this->options = $question['options'];

        // Derive majority aromatic family for the family chip
        $this->familyName = '';
        $this->familyColor = '';
        $groupTally = [];
        foreach ($this->options as $opt) {
            if (! empty($opt['groupName'])) {
                $groupTally[$opt['groupName']] = ($groupTally[$opt['groupName']] ?? 0) + 1;
            }
        }
        if ($groupTally) {
            arsort($groupTally);
            $this->familyName = (string) array_key_first($groupTally);
            foreach ($this->options as $opt) {
                if (($opt['groupName'] ?? '') === $this->familyName) {
                    $this->familyColor = (string) ($opt['color'] ?? '');
                    break;
                }
            }
        }

        // Store correct answer + step nonce in session (not in LiveProp).
        // Preserve correctSteps and questions accumulated across previous questions.
        $previous = $this->readSecret();
        $this->writeSecret([
            'correctAnswerId' => $question['correctAnswerId'],
            'currentStep' => $this->questionNumber,
            'answeredSteps' => $previous['answeredSteps'] ?? [],
            'correctSteps' => $previous['correctSteps'] ?? [],
            'questions' => $previous['questions'] ?? [],
        ]);
    }
}
