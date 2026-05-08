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
    use GameSessionTrait;

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
    public bool $isInCooldown = false;

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

        $gameDifficulty = GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY;
        $this->timeLimit = $this->academyManager->getChronoTimeLimit($gameDifficulty);

        $secret = [
            'startedAt' => $this->startedAt,
            'timeLimit' => $this->timeLimit,
        ];

        $this->generateQuestion($secret);
        $this->writeSecret($secret);
    }

    #[LiveAction]
    public function answer(#[LiveArg] string $spiceName): ?RedirectResponse
    {
        if ($this->isFinished) {
            return null;
        }

        $secret = $this->readSecret();
        $correctName = $secret['correctName'] ?? '';
        $questionStartedAt = $secret['questionStartedAt'] ?? null;

        if ($questionStartedAt === null) {
            return null;
        }

        // Anti-farming: ignore clicks during wrong-answer cooldown.
        if (time() < ($secret['wrongAnswerCooldown'] ?? 0)) {
            $this->isInCooldown = true;

            return null;
        }

        $this->isInCooldown = false;

        $serverCorrectCount = $secret['correctCount'] ?? 0;
        $serverQuestions = $secret['questionsAnswered'] ?? 0;
        $serverScore = $secret['totalScore'] ?? 0;
        $serverStreak = $secret['streak'] ?? 0;
        $serverStartedAt = (int) ($secret['startedAt'] ?? $this->startedAt);
        $serverTimeLimit = (int) ($secret['timeLimit'] ?? $this->timeLimit);

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

            $gameDifficulty = GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY;
            [$t1, $t2] = match ($gameDifficulty) {
                GameDifficulty::EASY => [8, 12],
                GameDifficulty::MEDIUM => [4, 8],
                GameDifficulty::HARD => [3, 6],
            };
            $base = match (true) {
                $serverElapsed < $t1 => 5,
                $serverElapsed < $t2 => 3,
                default => 1,
            };

            $streakBonus = min(max($serverStreak - 1, 0), 3);

            $points = $base + $streakBonus;
            $serverScore += $points;
            $this->totalScore += $points;
        } else {
            $serverStreak = 0;
            $this->streak = 0;
            $secret['wrongAnswerCooldown'] = time() + 2;
        }

        $this->lastPointsEarned = $points;

        $secret['correctCount'] = $serverCorrectCount;
        $secret['questionsAnswered'] = $serverQuestions;
        $secret['totalScore'] = $serverScore;
        $secret['streak'] = $serverStreak;
        $secret['questionStartedAt'] = null;

        if (time() - $serverStartedAt >= $serverTimeLimit + 3) {
            $this->writeSecret($secret);

            return $this->finish();
        }

        // generateQuestion modifie $secret par référence, writeSecret une seule fois
        $this->generateQuestion($secret);
        $this->writeSecret($secret);

        return null;
    }

    #[LiveAction]
    public function timeout(): ?RedirectResponse
    {
        if ($this->isFinished) {
            return null;
        }

        $secret = $this->readSecret();
        $serverStartedAt = (int) ($secret['startedAt'] ?? 0);
        $serverTimeLimit = (int) ($secret['timeLimit'] ?? $this->timeLimit);

        if ($serverStartedAt > 0 && time() - $serverStartedAt < $serverTimeLimit - 5) {
            return null;
        }

        return $this->finish();
    }

    #[LiveAction]
    public function finish(): RedirectResponse
    {
        /** @var Users $user */
        $user = $this->getUser();

        $secret = $this->readSecret();

        // Guard: orphan token (session already consumed or never started properly)
        if (empty($secret)) {
            return $this->redirectToRoute('education_index');
        }

        $serverCorrect = (int) ($secret['correctCount'] ?? 0);
        $serverQuestions = (int) ($secret['questionsAnswered'] ?? 0);
        $serverStartedAt = (int) ($secret['startedAt'] ?? $this->startedAt);

        $durationSeconds = time() - $serverStartedAt;
        $serverScore = (int) ($secret['totalScore'] ?? 0);

        $gameSession = $this->sessionManager->createFinishedSession(
            $user,
            GameMode::CHRONO,
            GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY,
            $serverCorrect,
            max($serverQuestions, 1),
            $durationSeconds,
            null,
            $serverScore,
        );

        $this->removeSecret();

        return $this->redirectToRoute('education_result', [
            'id' => $gameSession->getId(),
        ]);
    }

    /**
     * @param array<string, mixed> $secret passed by reference — caller is responsible for persisting to session
     */
    private function generateQuestion(array &$secret): void
    {
        $gameDifficulty = GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY;

        $card = $this->academyManager->getRandomSpiceCard($this->recentIds);

        if ($card === null) {
            $this->recentIds = [];
            $card = $this->academyManager->getRandomSpiceCard();
        }

        if ($card === null) {
            $this->isFinished = true;

            return;
        }

        $this->recentIds[] = $card['id'];

        if (\count($this->recentIds) > 5) {
            $this->recentIds = array_slice($this->recentIds, -5);
        }

        $this->currentCardId = (int) $card['id'];

        $optionsCount = $this->academyManager->getChronoOptionsCount($gameDifficulty);
        $this->nameOptions = $this->academyManager->generateNameOptions($card['name'], $optionsCount);

        // Modifie le secret par référence — le caller persiste en session
        $secret['correctName'] = $card['name'];
        $secret['questionStartedAt'] = time();
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
            GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY,
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
