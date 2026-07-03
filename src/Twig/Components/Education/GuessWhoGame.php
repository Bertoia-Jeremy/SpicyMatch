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
class GuessWhoGame extends AbstractController
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
    public int $totalQuestions = 5;

    #[LiveProp]
    public int $totalScore = 0;

    #[LiveProp]
    public int $correctCount = 0;

    #[LiveProp]
    public int $incorrectCount = 0;

    #[LiveProp]
    public int $currentClueIndex = 0;

    #[LiveProp]
    public int $totalCluesCount = 0;

    /**
     * @var array<array{type: string, label: string, value: string}>
     */
    #[LiveProp]
    public array $revealedClues = [];

    #[LiveProp]
    public bool $showFeedback = false;

    #[LiveProp]
    public ?bool $lastAnswerCorrect = null;

    #[LiveProp]
    public int $lastPointsEarned = 0;

    #[LiveProp]
    public string $lastCorrectName = '';

    #[LiveProp]
    public bool $isFinished = false;

    #[LiveProp]
    public int $startedAt = 0;

    /**
     * @var int[]
     */
    #[LiveProp]
    public array $usedSpiceIds = [];

    /**
     * @var string[]|null
     */
    private ?array $cachedSpiceNames = null;

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
        $this->generateQuestion();
    }

    /**
     * Returns all spice names for client-side autocomplete filtering.
     *
     * @return string[]
     */
    public function getAllSpiceNames(): array
    {
        if (null !== $this->cachedSpiceNames) {
            return $this->cachedSpiceNames;
        }

        $cards = $this->academyManager->getAllSpiceCards();
        $names = array_column($cards, 'name');
        sort($names);

        return $this->cachedSpiceNames = $names;
    }

    #[LiveAction]
    public function revealClue(): void
    {
        if ($this->showFeedback || $this->isFinished) {
            return;
        }

        $secret = $this->readSecret();
        $allClues = $secret['allClues'] ?? [];

        if ($this->currentClueIndex < \count($allClues)) {
            $this->revealedClues[] = $allClues[$this->currentClueIndex];
            ++$this->currentClueIndex;
        }
    }

    #[LiveAction]
    public function guess(#[LiveArg] string $spiceName): ?RedirectResponse
    {
        if ($this->showFeedback || $this->isFinished) {
            return null;
        }

        $secret = $this->readSecret();
        $correctName = $secret['correctName'] ?? '';
        $currentStep = $secret['currentStep'] ?? null;
        $answeredSteps = $secret['answeredSteps'] ?? [];

        // Replay guard
        if (null === $currentStep || \in_array($currentStep, $answeredSteps, true)) {
            return null;
        }

        // Validate against known spice names — reject freeform injected values
        if (! \in_array($spiceName, $this->getAllSpiceNames(), true)) {
            return null;
        }

        $answeredSteps[] = $currentStep;

        $isCorrect = $spiceName === $correctName;
        $correctSteps = $secret['correctSteps'] ?? [];
        $serverScore = $secret['totalScore'] ?? 0;

        $points = 0;

        if ($isCorrect) {
            $correctSteps[] = $currentStep;
            $cluesUsed = \count($this->revealedClues);
            $points = match (true) {
                $cluesUsed <= 1 => 10,
                2 === $cluesUsed => 8,
                3 === $cluesUsed => 6,
                4 === $cluesUsed => 4,
                default => 2,
            };
            $serverScore += $points;
            ++$this->correctCount;
            $this->totalScore += $points;
        } else {
            ++$this->incorrectCount;
        }

        // Store per-question history for result page
        $clueCount = \count($this->revealedClues);
        $questions = $secret['questions'] ?? [];
        $questions[] = [
            'questionIndex' => $this->questionNumber - 1,
            'prompt' => $this->translator->trans('ui.edu.prompt.guesswho_clues_used', [
                '%count%' => $clueCount,
            ]),
            'correctAnswer' => $correctName,
            'answerGiven' => $spiceName,
            'isCorrect' => $isCorrect,
        ];

        $secret['answeredSteps'] = $answeredSteps;
        $secret['correctSteps'] = $correctSteps;
        $secret['totalScore'] = $serverScore;
        $secret['questions'] = $questions;
        $this->writeSecret($secret);

        $this->lastAnswerCorrect = $isCorrect;
        $this->lastPointsEarned = $points;
        $this->lastCorrectName = $correctName;

        // Last question answered — skip feedback screen, redirect immediately
        if ($this->questionNumber >= $this->totalQuestions) {
            return $this->doFinish();
        }

        $this->showFeedback = true;

        return null;
    }

    #[LiveAction]
    public function next(): ?RedirectResponse
    {
        $this->showFeedback = false;
        $this->lastAnswerCorrect = null;
        $this->lastPointsEarned = 0;
        $this->lastCorrectName = '';

        if ($this->questionNumber >= $this->totalQuestions) {
            return $this->doFinish();
        }

        $this->generateQuestion();

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

        // Guard: already finishing (prevents double-call race)
        if ($secret['finished'] ?? false) {
            return $this->redirectToRoute('education_index');
        }

        $answeredSteps = $secret['answeredSteps'] ?? [];
        $correctSteps = $secret['correctSteps'] ?? [];
        $questionHistory = $secret['questions'] ?? [];

        // Guard: session already consumed (double-call prevention)
        if (empty($answeredSteps)) {
            return $this->redirectToRoute('education_index');
        }

        // Mark as finishing immediately to block concurrent calls
        $this->writeSecret(array_merge($secret, [
            'finished' => true,
        ]));

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::GUESS_WHO,
            GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY,
            \count($correctSteps),
            \count($answeredSteps),
            $durationSeconds,
        );

        $this->sessionManager->addQuestionsToSession($gameSession, $questionHistory);
        $this->removeSecret();

        return $this->redirectToRoute('education_result', [
            'id' => $gameSession->getId(),
        ]);
    }

    private function generateQuestion(): void
    {
        ++$this->questionNumber;
        $gameDifficulty = GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY;

        $minClues = match ($gameDifficulty) {
            GameDifficulty::EASY => 4,
            GameDifficulty::MEDIUM => 3,
            GameDifficulty::HARD => 2,
        };

        $cards = $this->academyManager->getAllSpiceCards();
        $eligible = array_filter(
            $cards,
            fn (array $c) => ! in_array($c['id'], $this->usedSpiceIds, true)
                && $this->academyManager->countAvailableClues($c) >= $minClues,
        );

        if (empty($eligible)) {
            $this->isFinished = true;
            --$this->questionNumber;

            return;
        }

        $card = $eligible[array_rand($eligible)];
        $this->usedSpiceIds[] = $card['id'];

        $allClues = $this->academyManager->generateGuessWhoClues($card, $gameDifficulty);
        $this->totalCluesCount = \count($allClues);
        $this->currentClueIndex = 1;
        $this->revealedClues = [$allClues[0]];

        // Preserve all accumulated session state — only reset the per-question keys
        $previous = $this->readSecret();
        $this->writeSecret([
            'correctName' => $card['name'],
            'currentStep' => $this->questionNumber,
            'answeredSteps' => $previous['answeredSteps'] ?? [],
            'correctSteps' => $previous['correctSteps'] ?? [],
            'totalScore' => $previous['totalScore'] ?? 0,
            'questions' => $previous['questions'] ?? [],
            'allClues' => $allClues,
        ]);
    }
}
