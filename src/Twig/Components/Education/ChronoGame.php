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
class ChronoGame extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $difficulty = 'easy';

    #[LiveProp]
    public string $gameToken = '';

    #[LiveProp]
    public int $questionsAnswered = 0;

    #[LiveProp]
    public int $correctCount = 0;

    #[LiveProp]
    public int $totalScore = 0;

    #[LiveProp]
    public int $streak = 0;

    #[LiveProp]
    public int $timeLimit = 120;

    /**
     * @var array<string, mixed> Spice card info (without name)
     */
    #[LiveProp]
    public array $currentCard = [];

    /**
     * @var string[]
     */
    #[LiveProp]
    public array $nameOptions = [];

    #[LiveProp]
    public bool $isFinished = false;

    #[LiveProp]
    public bool $lastAnswerCorrect = false;

    #[LiveProp]
    public bool $showFeedback = false;

    #[LiveProp]
    public string $lastCorrectName = '';

    #[LiveProp]
    public int $lastPointsEarned = 0;

    #[LiveProp]
    public int $startedAt = 0;

    /**
     * @var int[] Last 5 used spice IDs (not full history, to allow cycling)
     */
    #[LiveProp]
    public array $recentIds = [];

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
        $this->timeLimit = $this->academyManager->getChronoTimeLimit($gameDifficulty);

        $this->generateQuestion();

        // Store start time in session for anti-cheat
        $this->requestStack->getSession()
            ->set('game_' . $this->gameToken, [
                'correctName' => $this->requestStack->getSession()
                    ->get('game_' . $this->gameToken, [])['correctName'] ?? '',
                'startedAt' => $this->startedAt,
                'questionStartedAt' => time(),
            ]);
    }

    #[LiveAction]
    public function answer(string $spiceName): void
    {
        if ($this->isFinished) {
            return;
        }

        $session = $this->requestStack->getSession();
        $secret = $session->get('game_' . $this->gameToken, []);
        $correctName = $secret['correctName'] ?? '';
        $questionStartedAt = $secret['questionStartedAt'] ?? time();

        $isCorrect = $spiceName === $correctName;
        $serverElapsed = time() - $questionStartedAt;

        ++$this->questionsAnswered;
        $this->lastAnswerCorrect = $isCorrect;
        $this->lastCorrectName = $correctName;

        $points = 0;

        if ($isCorrect) {
            ++$this->correctCount;
            ++$this->streak;

            // Base points
            $base = 10;

            // Speed bonus based on server-side elapsed time
            if ($serverElapsed <= 3) {
                $base = (int) ($base * 2);
            } elseif ($serverElapsed <= 5) {
                $base = (int) ($base * 1.5);
            }

            // Streak bonus
            if ($this->streak >= 5) {
                $base = (int) ($base * 2);
            } elseif ($this->streak >= 3) {
                $base = (int) ($base * 1.5);
            }

            $points = $base;
            $this->totalScore += $points;
        } else {
            $this->streak = 0;
        }

        $this->lastPointsEarned = $points;

        // Check if global time is up (server-side validation)
        $totalElapsed = time() - $this->startedAt;

        if ($totalElapsed >= $this->timeLimit + 3) {
            $this->isFinished = true;

            return;
        }

        // Generate next question immediately (no feedback pause in chrono)
        $this->generateQuestion();
    }

    #[LiveAction]
    public function timeout(): void
    {
        if ($this->isFinished) {
            return;
        }

        // Server-side validation: check actual elapsed time
        $totalElapsed = time() - $this->startedAt;

        if ($totalElapsed < $this->timeLimit - 2) {
            return; // Suspicious early timeout, ignore
        }

        $this->isFinished = true;
    }

    #[LiveAction]
    public function finish(): RedirectResponse
    {
        /** @var Users $user */
        $user = $this->getUser();

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::CHRONO,
            GameDifficulty::from($this->difficulty),
            $this->correctCount,
            max($this->questionsAnswered, 1),
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
        $gameDifficulty = GameDifficulty::from($this->difficulty);

        $card = $this->academyManager->getRandomSpiceCard($this->recentIds);

        if ($card === null) {
            // Reset recent to allow cycling
            $this->recentIds = [];
            $card = $this->academyManager->getRandomSpiceCard();
        }

        if ($card === null) {
            $this->isFinished = true;

            return;
        }

        // Keep only last 5 in recent
        $this->recentIds[] = $card['id'];

        if (count($this->recentIds) > 5) {
            $this->recentIds = array_slice($this->recentIds, -5);
        }

        // Build display card (filter info by difficulty — hide name)
        $this->currentCard = $this->buildDisplayCard($card, $gameDifficulty);

        // Generate name options
        $optionsCount = $this->academyManager->getChronoOptionsCount($gameDifficulty);
        $this->nameOptions = $this->academyManager->generateNameOptions($card['name'], $optionsCount);

        // Store correct name in session
        $session = $this->requestStack->getSession();
        $secret = $session->get('game_' . $this->gameToken, []);
        $secret['correctName'] = $card['name'];
        $secret['questionStartedAt'] = time();
        $session->set('game_' . $this->gameToken, $secret);
    }

    /**
     * @param array<string, mixed> $card
     *
     * @return array<string, mixed>
     */
    private function buildDisplayCard(array $card, GameDifficulty $difficulty): array
    {
        // Common fields (no name!)
        $display = [];

        if ($difficulty === GameDifficulty::EASY) {
            // Full info: image, description, group, type, compounds, tips
            $display['file'] = $card['file'];
            $display['description'] = $card['description'];
            $display['aromaticGroup'] = $card['aromaticGroup'];
            $display['spicyType'] = $card['spicyType'];
            $display['mainCompounds'] = $card['mainCompounds'];
            $display['secondaryCompounds'] = $card['secondaryCompounds'];
            $display['alchemyFlavors'] = $card['alchemyFlavors'];
            $display['cookingTips'] = $card['cookingTips'];
        } elseif ($difficulty === GameDifficulty::MEDIUM) {
            // Partial: image, group, compounds (no description)
            $display['file'] = $card['file'];
            $display['aromaticGroup'] = $card['aromaticGroup'];
            $display['mainCompounds'] = $card['mainCompounds'];
            $display['secondaryCompounds'] = $card['secondaryCompounds'];
            $display['alchemyFlavors'] = $card['alchemyFlavors'];
        } else {
            // Hard: compounds and flavors only
            $display['mainCompounds'] = $card['mainCompounds'];
            $display['secondaryCompounds'] = $card['secondaryCompounds'];
            $display['alchemyFlavors'] = $card['alchemyFlavors'];
        }

        return $display;
    }
}
