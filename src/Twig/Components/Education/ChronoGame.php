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
     * ID of the currently displayed spice. The full card is rebuilt server-side
     * via `getCurrentCard()` at render time — this keeps the LC payload tiny
     * (was ~3-5 KB per re-render with the full `$currentCard` array).
     */
    #[LiveProp]
    public int $currentCardId = 0;

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

        // Init session avant generateQuestion (qui y mergera correctName + questionStartedAt)
        $this->requestStack->getSession()
            ->set('game_' . $this->gameToken, [
                'startedAt' => $this->startedAt,
            ]);

        $this->generateQuestion();
    }

    #[LiveAction]
    public function answer(#[LiveArg] string $spiceName): void
    {
        if ($this->isFinished) {
            return;
        }

        $session = $this->requestStack->getSession();
        $secretKey = 'game_' . $this->gameToken;
        $secret = $session->get($secretKey, []);
        $correctName = $secret['correctName'] ?? '';
        $questionStartedAt = $secret['questionStartedAt'] ?? null;

        // Replay guard: questionStartedAt is consumed on each answer → null until next generate.
        if ($questionStartedAt === null) {
            return;
        }

        $serverCorrectCount = $secret['correctCount'] ?? 0;
        $serverQuestions = $secret['questionsAnswered'] ?? 0;
        $serverScore = $secret['totalScore'] ?? 0;
        $serverStreak = $secret['streak'] ?? 0;

        $isCorrect = $spiceName === $correctName;
        $serverElapsed = time() - $questionStartedAt;

        ++$serverQuestions;
        ++$this->questionsAnswered;
        $this->lastAnswerCorrect = $isCorrect;
        $this->lastCorrectName = $correctName;

        $points = 0;

        if ($isCorrect) {
            ++$serverCorrectCount;
            ++$serverStreak;
            ++$this->correctCount;
            ++$this->streak;

            // Base points
            $base = 10;

            // Speed bonus — tightened thresholds to account for ~300 ms LC round-trip.
            if ($serverElapsed <= 2) {
                $base *= 2;
            } elseif ($serverElapsed <= 4) {
                $base = (int) ($base * 1.5);
            }

            // Streak bonus
            if ($serverStreak >= 5) {
                $base *= 2;
            } elseif ($serverStreak >= 3) {
                $base = (int) ($base * 1.5);
            }

            $points = $base;
            $serverScore += $points;
            $this->totalScore += $points;
        } else {
            $serverStreak = 0;
            $this->streak = 0;
        }

        $this->lastPointsEarned = $points;

        // Persist authoritative state to session; mirror kept on LiveProps for UI.
        $secret['correctCount'] = $serverCorrectCount;
        $secret['questionsAnswered'] = $serverQuestions;
        $secret['totalScore'] = $serverScore;
        $secret['streak'] = $serverStreak;
        $secret['questionStartedAt'] = null;
        $session->set($secretKey, $secret);

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

        // Server-side validation: only accept timeout once the actual limit has elapsed
        // (no early-finish window — prevents client triggering finish while the current
        // question still accepts answers).
        $totalElapsed = time() - $this->startedAt;

        if ($totalElapsed < $this->timeLimit) {
            return;
        }

        $this->isFinished = true;
    }

    #[LiveAction]
    public function finish(): RedirectResponse
    {
        /** @var Users $user */
        $user = $this->getUser();

        // Authoritative counts come from session, never LiveProps.
        $session = $this->requestStack->getSession();
        $secret = $session->get('game_' . $this->gameToken, []);
        $serverCorrect = (int) ($secret['correctCount'] ?? 0);
        $serverQuestions = (int) ($secret['questionsAnswered'] ?? 0);

        $durationSeconds = time() - $this->startedAt;

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::CHRONO,
            GameDifficulty::from($this->difficulty),
            $serverCorrect,
            max($serverQuestions, 1),
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

        // Only the ID travels to the client — full card is resolved at render time.
        $this->currentCardId = (int) $card['id'];

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
     * @var array<string, mixed>|null
     */
    private ?array $resolvedCardCache = null;

    /**
     * Resolve the current card at render time from the cached spice cards — keeps the
     * LiveProp payload lean (only `currentCardId` travels to the client).
     *
     * @return array<string, mixed>
     */
    public function getCurrentCard(): array
    {
        if ($this->resolvedCardCache !== null) {
            return $this->resolvedCardCache;
        }

        if ($this->currentCardId === 0) {
            return $this->resolvedCardCache = [];
        }

        $cards = $this->academyManager->getAllSpiceCards();
        if (! isset($cards[$this->currentCardId])) {
            return $this->resolvedCardCache = [];
        }

        return $this->resolvedCardCache = $this->buildDisplayCard(
            $cards[$this->currentCardId],
            GameDifficulty::from($this->difficulty),
        );
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
