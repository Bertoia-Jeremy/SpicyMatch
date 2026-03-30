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
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class GuessWhoGame extends AbstractController
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
    public int $totalScore = 0;

    #[LiveProp]
    public int $correctCount = 0;

    #[LiveProp]
    public int $currentClueIndex = 0;

    /**
     * @var array<array{type: string, label: string, value: string}>
     */
    #[LiveProp]
    public array $allClues = [];

    /**
     * @var array<array{type: string, label: string, value: string}>
     */
    #[LiveProp]
    public array $revealedClues = [];

    /**
     * @var string[]
     */
    #[LiveProp]
    public array $guessOptions = [];

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
        $this->generateQuestion();
    }

    #[LiveAction]
    public function revealClue(): void
    {
        if ($this->showFeedback || $this->isFinished) {
            return;
        }

        if ($this->currentClueIndex < count($this->allClues)) {
            $this->revealedClues[] = $this->allClues[$this->currentClueIndex];
            ++$this->currentClueIndex;
        }
    }

    #[LiveAction]
    public function guess(#[LiveArg] string $spiceName): void
    {
        if ($this->showFeedback || $this->isFinished) {
            return;
        }

        $session = $this->requestStack->getSession();
        $secret = $session->get('game_' . $this->gameToken, []);
        $correctName = $secret['correctName'] ?? '';

        $isCorrect = $spiceName === $correctName;

        // Scoring: 1 clue = 10, 2 = 8, 3 = 6, 4 = 4, 5+ = 2, wrong = 0
        $points = 0;

        if ($isCorrect) {
            ++$this->correctCount;
            $cluesUsed = count($this->revealedClues);
            $points = match (true) {
                $cluesUsed <= 1 => 10,
                $cluesUsed === 2 => 8,
                $cluesUsed === 3 => 6,
                $cluesUsed === 4 => 4,
                default => 2,
            };
            $this->totalScore += $points;
        }

        $this->lastAnswerCorrect = $isCorrect;
        $this->lastPointsEarned = $points;
        $this->lastCorrectName = $correctName;
        $this->showFeedback = true;
    }

    #[LiveAction]
    public function next(): void
    {
        $this->showFeedback = false;
        $this->lastAnswerCorrect = null;
        $this->lastPointsEarned = 0;
        $this->lastCorrectName = '';

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

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::GUESS_WHO,
            GameDifficulty::from($this->difficulty),
            $this->correctCount,
            $this->totalQuestions,
            $durationSeconds,
        );

        $this->requestStack->getSession()
            ->remove('game_' . $this->gameToken);

        return $this->redirectToRoute('education_result', [
            'id' => $gameSession->getId(),
        ]);
    }

    private function generateQuestion(): void
    {
        ++$this->questionNumber;
        $gameDifficulty = GameDifficulty::from($this->difficulty);

        // Min clues required by difficulty
        $minClues = match ($gameDifficulty) {
            GameDifficulty::EASY => 4,
            GameDifficulty::MEDIUM => 3,
            GameDifficulty::HARD => 2,
        };

        // Pick a spice with enough clue data
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

        // Generate clues
        $this->allClues = $this->academyManager->generateGuessWhoClues($card, $gameDifficulty);
        $this->currentClueIndex = 1; // Reveal first clue automatically
        $this->revealedClues = [$this->allClues[0]];

        // Generate options
        $optionsCount = $this->academyManager->getGuessWhoOptionsCount($gameDifficulty);
        $this->guessOptions = $this->academyManager->generateNameOptions($card['name'], $optionsCount);

        // Store correct answer in session
        $this->requestStack->getSession()
            ->set('game_' . $this->gameToken, [
                'correctName' => $card['name'],
            ]);
    }
}
