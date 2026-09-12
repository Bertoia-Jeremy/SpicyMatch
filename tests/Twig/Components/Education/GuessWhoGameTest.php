<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Education;

use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Education\GameSessionManager;
use App\Service\Match\CompatibleSpiceFinder;
use App\Twig\Components\Education\GuessWhoGame;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Translation\IdentityTranslator;

#[AllowMockObjectsWithoutExpectations]
final class GuessWhoGameTest extends TestCase
{
    private const string TOKEN = 'guesswho_test_tok';

    private AcademyManager&MockObject $academyManager;

    private GameSessionManager&MockObject $sessionManager;

    protected function setUp(): void
    {
        $this->academyManager = $this->createMock(AcademyManager::class);
        $this->sessionManager = $this->createMock(GameSessionManager::class);

        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                1 => [
                    'id' => 1,
                    'name' => 'Cannelle',
                ],
                2 => [
                    'id' => 2,
                    'name' => 'Cumin',
                ],
                3 => [
                    'id' => 3,
                    'name' => 'Poivre',
                ],
            ]);
    }

    /**
     * @param array<string, mixed> $secret
     *
     * @return array{GuessWhoGame, Session}
     */
    private function makeGame(array $secret = []): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('game_' . self::TOKEN, $secret);

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $game = new GuessWhoGame($this->academyManager, $this->sessionManager, $requestStack, new IdentityTranslator());
        $game->gameToken = self::TOKEN;
        $game->questionNumber = 1;
        $game->totalQuestions = 10;

        return [$game, $session];
    }

    /**
     * @param array<array{type: string, label: string, value: string}> $allClues
     *
     * @return array<string, mixed>
     */
    private function baseSecret(string $correctName = 'Cannelle', int $step = 1, array $allClues = []): array
    {
        return [
            'correctName' => $correctName,
            'currentStep' => $step,
            'answeredSteps' => [],
            'correctSteps' => [],
            'totalScore' => 0,
            'questions' => [],
            'allClues' => $allClues,
        ];
    }

    public function testRevealClueAppendsNextClueToRevealedClues(): void
    {
        $clues = [
            [
                'type' => 'description',
                'label' => 'Description',
                'value' => 'Épice chaude',
            ],
            [
                'type' => 'alchemyFlavors',
                'label' => 'Saveurs',
                'value' => 'Épicé, Chaud',
            ],
        ];
        [$game] = $this->makeGame($this->baseSecret(allClues: $clues));
        $game->revealedClues = [$clues[0]];
        $game->currentClueIndex = 1;

        $game->revealClue();

        self::assertCount(2, $game->revealedClues);
        self::assertSame(2, $game->currentClueIndex);
    }

    public function testRevealClueDoesNothingWhenShowFeedbackIsTrue(): void
    {
        $clues = [
            [
                'type' => 'description',
                'label' => 'Description',
                'value' => 'Épice chaude',
            ],
        ];
        [$game] = $this->makeGame($this->baseSecret(allClues: $clues));
        $game->currentClueIndex = 0;
        $game->showFeedback = true;

        $game->revealClue();

        self::assertCount(0, $game->revealedClues);
    }

    public function testRevealClueDoesNothingWhenIsFinished(): void
    {
        $clues = [
            [
                'type' => 'description',
                'label' => 'Description',
                'value' => 'Épice chaude',
            ],
        ];
        [$game] = $this->makeGame($this->baseSecret(allClues: $clues));
        $game->currentClueIndex = 0;
        $game->isFinished = true;

        $game->revealClue();

        self::assertCount(0, $game->revealedClues);
    }

    public function testRevealClueDoesNothingWhenAllCluesAlreadyRevealed(): void
    {
        $clues = [
            [
                'type' => 'description',
                'label' => 'Description',
                'value' => 'Épice chaude',
            ],
        ];
        [$game] = $this->makeGame($this->baseSecret(allClues: $clues));
        $game->revealedClues = [$clues[0]];
        $game->currentClueIndex = 1;

        $game->revealClue();

        self::assertCount(1, $game->revealedClues);
    }

    public function testGuessDoesNothingWhenShowFeedback(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->showFeedback = true;

        $result = $game->guess('Cannelle');

        self::assertNull($result);
        self::assertSame(0, $game->correctCount);
    }

    public function testGuessRejectsSpiceNameNotInWhitelist(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));

        $result = $game->guess('EpiceInconnue');

        self::assertNull($result);
        self::assertSame(0, $game->correctCount);
        self::assertFalse($game->showFeedback);
    }

    public function testGuessReplayGuardBlocksAlreadyAnsweredStep(): void
    {
        $secret = $this->baseSecret('Cannelle', step: 1);
        $secret['answeredSteps'] = [1];

        [$game] = $this->makeGame($secret);

        $result = $game->guess('Cannelle');

        self::assertNull($result);
        self::assertFalse($game->showFeedback);
    }

    public function testGuessCorrectAnswerIncrementsCorrectCount(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = [[
            'type' => 'description',
            'label' => 'D',
            'value' => 'v',
        ]];

        $game->guess('Cannelle');

        self::assertSame(1, $game->correctCount);
        self::assertSame(0, $game->incorrectCount);
    }

    public function testGuessCorrectAnswerWith1ClueEarns10Points(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = [[
            'type' => 'description',
            'label' => 'D',
            'value' => 'v',
        ]];

        $game->guess('Cannelle');

        self::assertSame(10, $game->lastPointsEarned);
        self::assertSame(10, $game->totalScore);
    }

    public function testGuessCorrectAnswerWith2CluesEarns8Points(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = [
            [
                'type' => 'description',
                'label' => 'D',
                'value' => 'v',
            ],
            [
                'type' => 'alchemyFlavors',
                'label' => 'S',
                'value' => 'w',
            ],
        ];

        $game->guess('Cannelle');

        self::assertSame(8, $game->lastPointsEarned);
    }

    public function testGuessCorrectAnswerWith3CluesEarns6Points(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = array_fill(0, 3, [
            'type' => 't',
            'label' => 'L',
            'value' => 'v',
        ]);

        $game->guess('Cannelle');

        self::assertSame(6, $game->lastPointsEarned);
    }

    public function testGuessCorrectAnswerWith5OrMoreCluesEarns2Points(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = array_fill(0, 5, [
            'type' => 't',
            'label' => 'L',
            'value' => 'v',
        ]);

        $game->guess('Cannelle');

        self::assertSame(2, $game->lastPointsEarned);
    }

    public function testGuessCorrectAnswerShowsFeedback(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = [[
            'type' => 'description',
            'label' => 'D',
            'value' => 'v',
        ]];

        $game->guess('Cannelle');

        self::assertTrue($game->lastAnswerCorrect);
        self::assertSame('Cannelle', $game->lastCorrectName);
        self::assertTrue($game->showFeedback);
    }

    public function testGuessWrongAnswerIncrementsIncorrectCount(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));

        $game->guess('Cumin');

        self::assertSame(0, $game->correctCount);
        self::assertSame(1, $game->incorrectCount);
    }

    public function testGuessWrongAnswerShowsFeedbackWithCorrectName(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));

        $game->guess('Cumin');

        self::assertFalse($game->lastAnswerCorrect);
        self::assertSame('Cannelle', $game->lastCorrectName);
        self::assertTrue($game->showFeedback);
    }

    public function testGuessWrongAnswerEarnsZeroPoints(): void
    {
        [$game] = $this->makeGame($this->baseSecret('Cannelle'));

        $game->guess('Cumin');

        self::assertSame(0, $game->lastPointsEarned);
        self::assertSame(0, $game->totalScore);
    }

    public function testGuessStoresAnsweredStepInSession(): void
    {
        [$game, $session] = $this->makeGame($this->baseSecret('Cannelle', step: 2));
        $game->questionNumber = 2;
        $game->revealedClues = [[
            'type' => 't',
            'label' => 'L',
            'value' => 'v',
        ]];

        $game->guess('Cannelle');

        $stored = $session->get('game_' . self::TOKEN);
        self::assertContains(2, $stored['answeredSteps']);
    }

    public function testGuessStoresScoreInSession(): void
    {
        [$game, $session] = $this->makeGame($this->baseSecret('Cannelle'));
        $game->revealedClues = [[
            'type' => 't',
            'label' => 'L',
            'value' => 'v',
        ]];

        $game->guess('Cannelle');

        $stored = $session->get('game_' . self::TOKEN);
        self::assertSame(10, $stored['totalScore']);
    }

    public function testNextResetsFeedbackState(): void
    {
        [$game] = $this->makeGame([
            'answeredSteps' => [],
            'correctSteps' => [],
            'totalScore' => 0,
            'questions' => [],
        ]);
        $game->showFeedback = true;
        $game->lastAnswerCorrect = true;
        $game->lastPointsEarned = 8;
        $game->lastCorrectName = 'Cannelle';
        $game->questionNumber = 2;
        $game->totalQuestions = 10;

        $this->academyManager->method('generateGuessWhoClues')
            ->willReturn([[
                'type' => 't',
                'label' => 'L',
                'value' => 'v',
            ]]);
        $this->academyManager->method('countAvailableClues')
            ->willReturn(4);

        $game->next();

        self::assertFalse($game->showFeedback);
        self::assertNull($game->lastAnswerCorrect);
        self::assertSame(0, $game->lastPointsEarned);
        self::assertSame('', $game->lastCorrectName);
    }

    public function testGetAllSpiceNamesReturnsTheLocalizedNames(): void
    {
        $this->stubLocalization();
        [$game] = $this->makeGame();

        self::assertSame(['Cinnamon', 'Cumin', 'Pepper'], $game->getAllSpiceNames());
    }

    public function testGetAllSpiceNamesFallsBackToCanonicalWhenNotEnriched(): void
    {
        [$game] = $this->makeGame();

        self::assertSame(['Cannelle', 'Cumin', 'Poivre'], $game->getAllSpiceNames());
    }

    public function testLastCorrectNameLabelIsLocalized(): void
    {
        $this->stubLocalization();
        [$game] = $this->makeGame();
        $game->lastCorrectName = 'Poivre';

        self::assertSame('Pepper', $game->getLastCorrectNameLabel());
    }

    #[DataProvider('provideGuessCandidates')]
    public function testGuessAcceptsLocaleAndFrenchNames(string $given, bool $expected): void
    {
        $this->stubLocalization();
        $secret = $this->baseSecret('Poivre');
        $secret['correctId'] = 3;

        [$game] = $this->makeGame($secret);
        $game->revealedClues = [[
            'type' => 'description',
            'label' => 'D',
            'value' => 'v',
        ]];

        $game->guess($given);

        self::assertSame($expected, $game->lastAnswerCorrect);
        self::assertSame($expected ? 1 : 0, $game->correctCount);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideGuessCandidates(): iterable
    {
        yield 'saisie dans la locale courante' => ['Pepper', true];
        yield 'saisie en français canonique' => ['Poivre', true];
        yield 'autre épice dans la locale courante' => ['Cinnamon', false];
        yield 'autre épice en français' => ['Cannelle', false];
    }

    public function testGuessStillRejectsAnUnknownName(): void
    {
        $this->stubLocalization();
        [$game] = $this->makeGame($this->baseSecret('Poivre'));

        $result = $game->guess('Peppercorn');

        self::assertNull($result);
        self::assertSame(0, $game->correctCount);
        self::assertFalse($game->showFeedback);
    }

    public function testGuessPersistsTheCanonicalNameOfALocalizedAnswer(): void
    {
        $this->stubLocalization();
        $secret = $this->baseSecret('Poivre');
        $secret['correctId'] = 3;

        [$game, $session] = $this->makeGame($secret);

        $game->guess('Cinnamon');

        $stored = $session->get('game_' . self::TOKEN);
        self::assertSame('Cannelle', $stored['questions'][0]['answerGiven']);
        self::assertSame('Poivre', $stored['questions'][0]['correctAnswer']);
    }

    public function testFrenchRenderIssuesNoEnrichmentQuery(): void
    {
        $repository = $this->createMock(SpicesRepository::class);
        $repository->expects(self::never())
            ->method('findEnrichedByIds');

        $secret = $this->baseSecret('Poivre');
        $secret['correctId'] = 3;

        $game = $this->makeGameWithRealManager('fr', $repository, $secret);

        $game->guess('Poivre');

        self::assertSame(['Cannelle', 'Cumin', 'Poivre'], $game->getAllSpiceNames());
        self::assertTrue($game->lastAnswerCorrect);
        self::assertSame('Poivre', $game->getLastCorrectNameLabel());
    }

    public function testEnglishScreenIssuesASingleEnrichmentQuery(): void
    {
        $repository = $this->createMock(SpicesRepository::class);
        $repository->expects(self::once())
            ->method('findEnrichedByIds')
            ->with([1, 2, 3], 'en')
            ->willReturn([
                $this->enrichedRow(1, 'Cinnamon'),
                $this->enrichedRow(2, 'Cumin'),
                $this->enrichedRow(3, 'Pepper'),
            ]);

        $secret = $this->baseSecret('Poivre');
        $secret['correctId'] = 3;

        $game = $this->makeGameWithRealManager('en', $repository, $secret);

        $game->guess('Pepper');

        self::assertTrue($game->lastAnswerCorrect);
        self::assertSame(['Cinnamon', 'Cumin', 'Pepper'], $game->getAllSpiceNames());
        self::assertSame('Pepper', $game->getLastCorrectNameLabel());
    }

    /**
     * @return array{id: int, name: string, slug: ?string, file: ?string, agId: ?int, color: ?string, groupName: ?string, stId: ?int, typeName: ?string}
     */
    private function enrichedRow(int $id, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'slug' => null,
            'file' => null,
            'agId' => null,
            'color' => null,
            'groupName' => null,
            'stId' => null,
            'typeName' => null,
        ];
    }

    private function stubLocalization(): void
    {
        $translations = [
            1 => 'Cinnamon',
            2 => 'Cumin',
            3 => 'Pepper',
        ];

        $this->academyManager->method('localizeSpiceSummaries')
            ->willReturnCallback(static fn (array $summaries): array => array_map(
                static fn (array $summary): array => [
                    ...$summary,
                    'name' => $translations[$summary['id']] ?? $summary['name'],
                ],
                $summaries,
            ));
    }

    /**
     * @param SpicesRepository&MockObject $repository
     * @param array<string, mixed>        $secret
     */
    private function makeGameWithRealManager(string $locale, SpicesRepository $repository, array $secret): GuessWhoGame
    {
        $translator = new IdentityTranslator();
        $translator->setLocale($locale);

        $cache = new ArrayAdapter();
        $cache->get('academy.spice_cards', static fn (): array => [
            1 => [
                'id' => 1,
                'name' => 'Cannelle',
            ],
            2 => [
                'id' => 2,
                'name' => 'Cumin',
            ],
            3 => [
                'id' => 3,
                'name' => 'Poivre',
            ],
        ]);

        $manager = new AcademyManager(
            $repository,
            $this->createStub(CompatibleSpiceFinder::class),
            $cache,
            $translator,
        );

        $session = new Session(new MockArraySessionStorage());
        $session->set('game_' . self::TOKEN, $secret);

        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $game = new GuessWhoGame($manager, $this->sessionManager, $requestStack, $translator);
        $game->gameToken = self::TOKEN;
        $game->questionNumber = 1;
        $game->totalQuestions = 10;
        $game->revealedClues = [[
            'type' => 'description',
            'label' => 'D',
            'value' => 'v',
        ]];

        return $game;
    }
}
