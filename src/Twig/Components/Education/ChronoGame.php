<?php

declare(strict_types=1);

namespace App\Twig\Components\Education;

use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Service\Education\AcademyManager;
use App\Service\Education\GameSessionManager;
use App\Service\Education\LocalizedSpiceNames;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
#[IsGranted('ROLE_USER')]
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
     * @var int[]
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

        $isCorrect = $this->matchesExpectedName($spiceName, $correctName, (int) ($secret['correctId'] ?? 0));
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
     * @param array<string, mixed> $secret
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

        $secret['correctName'] = $card['name'];
        $secret['correctId'] = (int) $card['id'];
        $secret['questionStartedAt'] = time();
    }

    /**
     * @var array<string, mixed>|null
     */
    private ?array $resolvedCardCache = null;

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $spiceCardsCache = null;

    /**
     * @var array<int, array{canonical: string, localized: string, groupName: ?string}>|null
     */
    private ?array $nameMapCache = null;

    /**
     * @var array<string, string>|null
     */
    private ?array $labelByCanonicalCache = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    private function spiceCards(): array
    {
        return $this->spiceCardsCache ??= $this->academyManager->getAllSpiceCards();
    }

    /**
     * @return array<int, array{canonical: string, localized: string, groupName: ?string}>
     */
    private function nameMap(): array
    {
        return $this->nameMapCache ??= LocalizedSpiceNames::build($this->spiceCards(), $this->academyManager);
    }

    private function localizedLabel(string $canonical): string
    {
        return LocalizedSpiceNames::label(
            $canonical,
            $this->labelByCanonicalCache ??= LocalizedSpiceNames::labelIndex($this->nameMap()),
        );
    }

    private function matchesExpectedName(string $given, string $expected, int $expectedId): bool
    {
        return LocalizedSpiceNames::matches($given, $expected, $expectedId, $this->nameMap());
    }

    /**
     * @return list<string>
     */
    public function getLocalizedNameOptions(): array
    {
        return array_values(array_map(
            fn (string $name): string => $this->localizedLabel($name),
            $this->nameOptions,
        ));
    }

    public function getLastCorrectNameLabel(): string
    {
        return $this->localizedLabel($this->lastCorrectName);
    }

    /**
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

        $cards = $this->spiceCards();
        if (! isset($cards[$this->currentCardId])) {
            return $this->resolvedCardCache = [];
        }

        $display = $this->buildDisplayCard(
            $cards[$this->currentCardId],
            GameDifficulty::tryFrom($this->difficulty) ?? GameDifficulty::EASY,
        );

        $group = $display['aromaticGroup'] ?? null;
        $localizedGroup = $this->nameMap()[$this->currentCardId]['groupName'] ?? null;

        if (\is_array($group) && $localizedGroup !== null) {
            $group['name'] = $localizedGroup;
            $display['aromaticGroup'] = $group;
        }

        return $this->resolvedCardCache = $display;
    }

    /**
     * @param array<string, mixed> $card
     *
     * @return array<string, mixed>
     */
    private function buildDisplayCard(array $card, GameDifficulty $difficulty): array
    {
        $display = [];

        if ($difficulty === GameDifficulty::EASY) {
            $display['file'] = $card['file'];
            $display['description'] = $card['description'];
            $display['aromaticGroup'] = $card['aromaticGroup'];
            $display['spicyType'] = $card['spicyType'];
            $display['mainCompounds'] = $card['mainCompounds'];
            $display['secondaryCompounds'] = $card['secondaryCompounds'];
            $display['alchemyFlavors'] = $card['alchemyFlavors'];
            $display['cookingTips'] = $card['cookingTips'];
        } elseif ($difficulty === GameDifficulty::MEDIUM) {
            $display['file'] = $card['file'];
            $display['aromaticGroup'] = $card['aromaticGroup'];
            $display['mainCompounds'] = $card['mainCompounds'];
            $display['secondaryCompounds'] = $card['secondaryCompounds'];
            $display['alchemyFlavors'] = $card['alchemyFlavors'];
        } else {
            $display['mainCompounds'] = $card['mainCompounds'];
            $display['secondaryCompounds'] = $card['secondaryCompounds'];
            $display['alchemyFlavors'] = $card['alchemyFlavors'];
        }

        return $display;
    }
}
