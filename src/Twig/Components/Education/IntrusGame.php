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

    #[LiveProp]
    public string $difficulty = 'easy';

    #[LiveProp]
    public string $gameToken = '';

    #[LiveProp]
    public int $questionNumber = 0;

    #[LiveProp]
    public int $totalQuestions = 10;

    #[LiveProp]
    public int $correctCount = 0;

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

        $session = $this->requestStack->getSession();
        $secretKey = 'game_' . $this->gameToken;
        $secret = $session->get($secretKey, []);
        $correctId = $secret['correctAnswerId'] ?? null;
        $currentStep = $secret['currentStep'] ?? null;
        $answeredSteps = $secret['answeredSteps'] ?? [];
        $correctSteps = $secret['correctSteps'] ?? [];

        // Replay guard: each question (identified by step) can only be answered once.
        if ($currentStep === null || \in_array($currentStep, $answeredSteps, true)) {
            return;
        }

        $answeredSteps[] = $currentStep;

        $isCorrect = $spiceId === $correctId;
        if ($isCorrect) {
            $correctSteps[] = $currentStep;
        }

        $secret['answeredSteps'] = $answeredSteps;
        $secret['correctSteps'] = $correctSteps;
        $session->set($secretKey, $secret);

        // Mirror to LiveProp for UI only — server-side source of truth is the session.
        if ($isCorrect) {
            ++$this->correctCount;
        }

        // Find the correct answer name for feedback
        $correctName = '';

        foreach ($this->options as $opt) {
            if ($opt['id'] === $correctId) {
                $correctName = $opt['name'];
                break;
            }
        }

        $this->answers[] = [
            'correct' => $isCorrect,
        ];
        $this->lastAnswerCorrect = $isCorrect;
        $this->lastCorrectAnswerName = $correctName;
        $this->showFeedback = true;
    }

    #[LiveAction]
    public function next(): void
    {
        $this->showFeedback = false;
        $this->lastAnswerCorrect = null;
        $this->lastCorrectAnswerName = '';

        if ($this->questionNumber >= $this->totalQuestions) {
            $this->isFinished = true;

            return;
        }

        $this->generateQuestion();
    }

    #[LiveAction]
    public function finish(): RedirectResponse
    {
        /** @var Users $user */
        $user = $this->getUser();

        // Authoritative counts come from session — NOT from LiveProps (client-tamperable).
        $session = $this->requestStack->getSession();
        $secret = $session->get('game_' . $this->gameToken, []);
        $answeredSteps = $secret['answeredSteps'] ?? [];
        $correctSteps = $secret['correctSteps'] ?? [];

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::INTRUS,
            GameDifficulty::from($this->difficulty),
            \count($correctSteps),
            \count($answeredSteps),
            $durationSeconds,
        );

        // Cleanup session secrets
        $session->remove('game_' . $this->gameToken);

        return $this->redirectToRoute('education_result', [
            'id' => $gameSession->getId(),
        ]);
    }

    private function generateQuestion(): void
    {
        ++$this->questionNumber;
        $gameDifficulty = GameDifficulty::from($this->difficulty);

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

        // Store correct answer + step nonce in session (not in LiveProp)
        $session = $this->requestStack->getSession();
        $key = 'game_' . $this->gameToken;
        $previous = $session->get($key, []);
        $session->set($key, [
            'correctAnswerId' => $question['correctAnswerId'],
            'currentStep' => $this->questionNumber,
            'answeredSteps' => $previous['answeredSteps'] ?? [],
        ]);
    }
}
