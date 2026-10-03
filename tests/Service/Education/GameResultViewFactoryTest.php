<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Entity\GameQuestion;
use App\Entity\GameSession;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Enum\RecordState;
use App\Enum\ResultTier;
use App\Repository\GameSessionRepository;
use App\Repository\ProcessedGamificationEventRepository;
use App\Repository\SpicesRepository;
use App\Service\Education\DifficultyAdvisor;
use App\Service\Education\GameResultViewFactory;
use App\Service\Education\GameSessionManager;
use App\Service\Education\SkillAssessor;
use App\ValueObject\Education\ResultAnswer;
use PHPUnit\Framework\TestCase;

final class GameResultViewFactoryTest extends TestCase
{
    private const array CARDS = [
        1 => [
            'name' => 'Cumin',
            'slug' => 'cumin',
            'active' => true,
        ],
        2 => [
            'name' => 'Carvi',
            'slug' => 'carvi',
            'active' => true,
        ],
        3 => [
            'name' => 'Poivre',
            'slug' => 'poivre',
            'active' => true,
        ],
        4 => [
            'name' => 'Muscade',
            'slug' => 'muscade',
            'active' => false,
        ],
        5 => [
            'name' => 'Cannelle',
            'slug' => 'cannelle',
            'active' => true,
        ],
        6 => [
            'name' => 'Badiane',
            'slug' => 'badiane',
            'active' => true,
        ],
        7 => [
            'name' => 'Sumac',
            'slug' => 'sumac',
            'active' => true,
        ],
    ];

    public function testBuildProjectsPendingXpAndCollectsMissedSpices(): void
    {
        $user = $this->user(200);
        $session = $this->session($user, GameMode::QCM, 40, [
            [true, 1, 2, 2],
            [false, 1, 2, 3],
            [false, 1, 2, 5],
            [false, 1, 4, 3],
            [false, 5, 6, 3],
            [false, 5, 7, 3],
            [false, 5, 3, 1],
        ]);

        $view = $this->factory(processed: false, previousBest: 30, accuracies: [])->build($session, $user, 'fr');

        self::assertNotNull($view->xp);
        self::assertSame(40, $view->xp->gained);
        self::assertSame(240, $view->xp->total);
        self::assertTrue($view->xp->pending);
        self::assertSame(RecordState::NEW, $view->record);
        self::assertSame(30, $view->previousBest);
        self::assertTrue($view->canReplay);
        self::assertSame(
            [['Carvi', 'Cumin', 'Poivre'], ['Badiane', 'Cannelle', 'Poivre'], ['Sumac', 'Cannelle', 'Poivre']],
            array_map(static fn ($m): array => [$m->name, $m->contextName, $m->given], $view->missed),
        );
        self::assertSame('carvi', $view->missed[0]->slug);
        self::assertSame(6, $view->wrongAnswers());
        self::assertSame(ResultTier::FIRST_STEPS, $view->tier());
    }

    public function testBuildDoesNotCountAlreadyProcessedXpTwice(): void
    {
        $user = $this->user(240);
        $session = $this->session($user, GameMode::QCM, 40, [[true, 1, 2, 2]]);

        $view = $this->factory(processed: true, previousBest: null, accuracies: [])->build($session, $user, 'fr');

        self::assertNotNull($view->xp);
        self::assertSame(240, $view->xp->total);
        self::assertFalse($view->xp->pending);
        self::assertSame(RecordState::FIRST, $view->record);
    }

    public function testBuildHidesProgressWhenGamificationIsDisabled(): void
    {
        $user = $this->user(200);
        $user->getProgression()?->disableGamification();
        $session = $this->session($user, GameMode::QCM, 40, [[false, 1, 2, 3]]);

        $view = $this->factory(processed: false, previousBest: 10, accuracies: [90.0, 90.0, 90.0])->build($session, $user, 'fr');

        self::assertNull($view->xp);
        self::assertNull($view->record);
        self::assertNull($view->previousBest);
        self::assertFalse($view->canReplay);
        self::assertNull($view->nextStep);
        self::assertCount(1, $view->missed);
    }

    public function testBuildSuggestsPromotionFromRecentAccuracies(): void
    {
        $user = $this->user(0);
        $session = $this->session($user, GameMode::QCM, 10, [[false, 1, 2, 3]]);

        $view = $this->factory(processed: false, previousBest: null, accuracies: [90.0, 85.0, 80.0])->build($session, $user, 'fr');

        self::assertNotNull($view->nextStep);
        self::assertSame(GameDifficulty::MEDIUM, $view->nextStep->difficulty);
        self::assertTrue($view->nextStep->promotion);
        self::assertSame(85, $view->nextStep->winratePct);
        self::assertSame(3, $view->nextStep->samples);
    }

    public function testBuildSuggestsHarderDifficultyAfterPerfectGameWithoutHistory(): void
    {
        $user = $this->user(0);
        $session = $this->session($user, GameMode::QCM, 10, [[true, 1, 2, 2], [true, 1, 3, 3]]);

        $view = $this->factory(processed: false, previousBest: null, accuracies: [])->build($session, $user, 'fr');

        self::assertNotNull($view->nextStep);
        self::assertSame(GameDifficulty::MEDIUM, $view->nextStep->difficulty);
        self::assertNull($view->nextStep->winratePct);
        self::assertSame([], $view->missed);
    }

    public function testBuildBlocksReplayWhenDailyQuotaIsReached(): void
    {
        $user = $this->user(0);
        $session = $this->session($user, GameMode::QCM, 10, [[true, 1, 2, 2]]);

        $view = $this->factory(
            processed: false,
            previousBest: null,
            accuracies: [],
            played: GameSessionManager::MAX_DAILY_SESSIONS_FREE,
        )->build($session, $user, 'fr');

        self::assertFalse($view->canReplay);
        self::assertNull($view->nextStep);
    }

    public function testBuildFallsBackToStoredStringsForRowsWithoutIds(): void
    {
        $user = $this->user(0);
        $session = $this->session($user, GameMode::GUESS_WHO, 0, []);
        $question = new GameQuestion()
            ->setQuestionIndex(0)
            ->setQuestionData([
                'prompt' => 'Graine ambrée',
                'correctAnswer' => 'Fenugrec',
            ]);
        $question->answer(str_repeat('x', 200), false);
        $session->addQuestion($question);

        $view = $this->factory(processed: false, previousBest: null, accuracies: [])->build($session, $user, 'fr');

        self::assertSame([], $view->missed);
        self::assertEquals(
            [new ResultAnswer(1, 'Graine ambrée', str_repeat('x', GameResultViewFactory::MAX_ANSWER_LENGTH - 1) . '…', 'Fenugrec', false)],
            $view->answers,
        );
    }

    public function testBuildRanksSurvivalOnChainLength(): void
    {
        $user = $this->user(0);
        $session = $this->session($user, GameMode::SURVIVAL, 30, []);
        for ($i = 0; $i < 7; ++$i) {
            $session->incrementCorrectAnswers();
        }

        $view = $this->factory(processed: false, previousBest: null, accuracies: [])->build($session, $user, 'fr');

        self::assertTrue($view->isChain());
        self::assertSame(ResultTier::KEEN_NOSE, $view->tier());
        self::assertNull($view->accuracyPct());
        self::assertSame([], $view->answers);
        self::assertNull($view->nextStep);
    }

    /**
     * @param list<float> $accuracies
     */
    private function factory(bool $processed, ?int $previousBest, array $accuracies, int $played = 0): GameResultViewFactory
    {
        $sessionRepository = $this->createStub(GameSessionRepository::class);
        $sessionRepository->method('findBestScoreBefore')
            ->willReturn($previousBest);
        $sessionRepository->method('findRecentAccuracies')
            ->willReturn($accuracies);

        $sessionManager = $this->createStub(GameSessionManager::class);
        $sessionManager->method('countTodaySessions')
            ->willReturn($played);
        $sessionManager->method('maxDailySessions')
            ->willReturn(GameSessionManager::MAX_DAILY_SESSIONS_FREE);

        $spicesRepository = $this->createStub(SpicesRepository::class);
        $spicesRepository->method('findResultCards')
            ->willReturnCallback(static fn (array $ids): array => array_intersect_key(self::CARDS, array_flip($ids)));

        $processedEvents = $this->createStub(ProcessedGamificationEventRepository::class);
        $processedEvents->method('findXpSnapshot')
            ->willReturnCallback(static fn (Users $user): array => [
                'xp' => $user->getProgression()?->getXp() ?? 0,
                'processed' => $processed,
            ]);

        return new GameResultViewFactory(
            $sessionRepository,
            $sessionManager,
            new DifficultyAdvisor($sessionRepository, new SkillAssessor()),
            $spicesRepository,
            $processedEvents,
        );
    }

    private function user(int $xp): Users
    {
        $user = new Users();
        $progression = new UserProgression();
        $progression->addXp($xp);
        $user->setProgression($progression);

        return $user;
    }

    /**
     * @param list<array{bool, int, int, int}> $rows
     */
    private function session(Users $user, GameMode $mode, int $score, array $rows): GameSession
    {
        $session = new GameSession()
            ->setUser($user)
            ->setGameMode($mode)
            ->setDifficulty(GameDifficulty::EASY)
            ->setScore($score)
            ->setTotalQuestions(\count($rows));

        foreach ($rows as $index => [$correct, $questionId, $correctId, $givenId]) {
            $question = new GameQuestion()
                ->setQuestionIndex($index)
                ->setQuestionData([
                    'questionSpiceId' => $questionId,
                    'correctSpiceId' => $correctId,
                    'givenSpiceId' => $givenId,
                ]);
            $question->answer((string) $givenId, $correct);
            $session->addQuestion($question);

            if ($correct) {
                $session->incrementCorrectAnswers();
            }
        }

        $session->finish();

        return $session;
    }
}
