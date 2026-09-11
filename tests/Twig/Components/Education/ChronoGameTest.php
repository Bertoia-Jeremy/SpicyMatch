<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components\Education;

use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Education\GameSessionManager;
use App\Service\Match\CompatibleSpiceFinder;
use App\Twig\Components\Education\ChronoGame;
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
final class ChronoGameTest extends TestCase
{
    private const string TOKEN = 'chrono_test_tok';

    private AcademyManager&MockObject $academyManager;
    private GameSessionManager&MockObject $sessionManager;

    protected function setUp(): void
    {
        $this->academyManager = $this->createMock(AcademyManager::class);
        $this->sessionManager = $this->createMock(GameSessionManager::class);
    }

    /**
     * @param array<string, mixed> $secret
     *
     * @return array{ChronoGame, Session}
     */
    private function makeGame(array $secret = []): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('game_'.self::TOKEN, $secret);

        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $game = new ChronoGame($this->academyManager, $this->sessionManager, $requestStack);
        $game->gameToken = self::TOKEN;
        $game->difficulty = 'easy';
        $game->timeLimit = 90;
        $game->startedAt = time();

        return [$game, $session];
    }

    /**
     * @return array<string, mixed>
     */
    private function inProgressSecret(string $correctName = 'Cannelle', int $timeOffset = 0): array
    {
        return [
            'correctName' => $correctName,
            'questionStartedAt' => time() - $timeOffset,
            'correctCount' => 0,
            'questionsAnswered' => 0,
            'totalScore' => 0,
            'streak' => 0,
            'startedAt' => time(),
            'timeLimit' => 90,
        ];
    }

    public function testGetCurrentCardReturnsEmptyWhenCurrentCardIdIsZero(): void
    {
        [$game] = $this->makeGame();
        $game->currentCardId = 0;

        $card = $game->getCurrentCard();

        self::assertSame([], $card);
    }

    public function testGetCurrentCardReturnsEmptyWhenCardNotFound(): void
    {
        [$game] = $this->makeGame();
        $game->currentCardId = 999;
        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([]);

        $card = $game->getCurrentCard();

        self::assertSame([], $card);
    }

    public function testGetCurrentCardEasyDifficultyReturnsAllFields(): void
    {
        [$game] = $this->makeGame();
        $game->currentCardId = 1;
        $game->difficulty = 'easy';

        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                1 => [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'file' => 'cannelle.jpg',
                    'description' => 'Épice chaude',
                    'aromaticGroup' => [
                        'name' => 'Aromatiques',
                    ],
                    'spicyType' => 'Herbacé',
                    'mainCompounds' => ['Cinnamaldéhyde'],
                    'secondaryCompounds' => [],
                    'alchemyFlavors' => ['Chaud'],
                    'cookingTips' => [],
                ],
            ]);

        $card = $game->getCurrentCard();

        self::assertArrayHasKey('file', $card);
        self::assertArrayHasKey('description', $card);
        self::assertArrayHasKey('mainCompounds', $card);
        self::assertArrayNotHasKey('id', $card);
        self::assertArrayNotHasKey('name', $card);
    }

    public function testGetCurrentCardHardDifficultyReturnsOnlyCompoundsAndFlavors(): void
    {
        [$game] = $this->makeGame();
        $game->currentCardId = 1;
        $game->difficulty = 'hard';

        $this->academyManager->method('getAllSpiceCards')
            ->willReturn([
                1 => [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'file' => 'cannelle.jpg',
                    'description' => 'Épice chaude',
                    'aromaticGroup' => [
                        'name' => 'Aromatiques',
                    ],
                    'spicyType' => 'Herbacé',
                    'mainCompounds' => ['Cinnamaldéhyde'],
                    'secondaryCompounds' => [],
                    'alchemyFlavors' => ['Chaud'],
                    'cookingTips' => [],
                ],
            ]);

        $card = $game->getCurrentCard();

        self::assertArrayHasKey('mainCompounds', $card);
        self::assertArrayHasKey('alchemyFlavors', $card);
        self::assertArrayNotHasKey('file', $card);
        self::assertArrayNotHasKey('description', $card);
        self::assertArrayNotHasKey('aromaticGroup', $card);
    }

    public function testGetCurrentCardIsMemoizedOnSecondCall(): void
    {
        [$game] = $this->makeGame();
        $game->currentCardId = 1;

        $this->academyManager->expects(self::once())
            ->method('getAllSpiceCards')
            ->willReturn([
                1 => [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'file' => null,
                    'description' => '',
                    'aromaticGroup' => [],
                    'spicyType' => '',
                    'mainCompounds' => [],
                    'secondaryCompounds' => [],
                    'alchemyFlavors' => [],
                    'cookingTips' => [],
                ],
            ]);

        $game->getCurrentCard();
        $game->getCurrentCard();
    }

    public function testAnswerDoesNothingWhenIsFinished(): void
    {
        [$game] = $this->makeGame($this->inProgressSecret());
        $game->isFinished = true;

        $result = $game->answer('Cannelle');

        self::assertNull($result);
        self::assertSame(0, $game->correctCount);
    }

    public function testAnswerReturnsNullWhenQuestionStartedAtIsNull(): void
    {
        $secret = $this->inProgressSecret();
        $secret['questionStartedAt'] = null;

        [$game] = $this->makeGame($secret);

        $result = $game->answer('Cannelle');

        self::assertNull($result);
        self::assertSame(0, $game->correctCount);
    }

    public function testAnswerReturnsNullDuringWrongAnswerCooldown(): void
    {
        $secret = $this->inProgressSecret();
        $secret['wrongAnswerCooldown'] = time() + 10;

        [$game] = $this->makeGame($secret);

        $result = $game->answer('Cannelle');

        self::assertNull($result);
        self::assertTrue($game->isInCooldown);
    }

    public function testAnswerCorrectIncrementsCorrectCountAndStreak(): void
    {
        [$game] = $this->makeGame($this->inProgressSecret('Cannelle'));

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cannelle');

        self::assertSame(1, $game->correctCount);
        self::assertSame(1, $game->streak);
    }

    public function testAnswerCorrectUpdatesLastAnswerCorrectAndName(): void
    {
        [$game] = $this->makeGame($this->inProgressSecret('Cannelle'));

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cannelle');

        self::assertTrue($game->lastAnswerCorrect);
        self::assertSame('Cannelle', $game->lastCorrectName);
    }

    public function testAnswerCorrectEarnsAtLeastOnePoint(): void
    {
        [$game] = $this->makeGame($this->inProgressSecret('Cannelle', timeOffset: 30));

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cannelle');

        self::assertGreaterThanOrEqual(1, $game->lastPointsEarned);
    }

    public function testAnswerWrongResetsStreak(): void
    {
        $secret = $this->inProgressSecret('Cannelle');
        $secret['streak'] = 3;

        [$game] = $this->makeGame($secret);
        $game->streak = 3;

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cumin');

        self::assertSame(0, $game->streak);
        self::assertFalse($game->lastAnswerCorrect);
    }

    public function testAnswerWrongSetsCooldownInSession(): void
    {
        [$game, $session] = $this->makeGame($this->inProgressSecret('Cannelle'));

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cumin');

        $stored = $session->get('game_'.self::TOKEN);
        self::assertArrayHasKey('wrongAnswerCooldown', $stored);
        self::assertGreaterThan(time(), $stored['wrongAnswerCooldown']);
    }

    public function testAnswerWrongEarnsZeroPoints(): void
    {
        [$game] = $this->makeGame($this->inProgressSecret('Cannelle'));

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cumin');

        self::assertSame(0, $game->lastPointsEarned);
    }

    public function testAnswerCorrectFastResponseEarnsHighBasePoints(): void
    {
        $secret = $this->inProgressSecret('Cannelle', timeOffset: 2);

        [$game] = $this->makeGame($secret);
        $game->difficulty = 'easy';

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cannelle');

        self::assertSame(5, $game->lastPointsEarned);
    }

    public function testAnswerCorrectStreakBonusMaxesAt3(): void
    {
        $secret = $this->inProgressSecret('Cannelle', timeOffset: 1);
        $secret['streak'] = 4;

        [$game] = $this->makeGame($secret);
        $game->difficulty = 'easy';
        $game->streak = 4;

        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer('Cannelle');

        self::assertSame(8, $game->lastPointsEarned);
    }

    public function testLocalizedNameOptionsReturnTheLocalizedNames(): void
    {
        $this->stubLocalizedCards();
        [$game] = $this->makeGame();
        $game->nameOptions = ['Poivre', 'Cannelle'];

        self::assertSame(['Pepper', 'Cinnamon'], $game->getLocalizedNameOptions());
    }

    public function testLocalizedNameOptionsFallBackToCanonicalWhenNotEnriched(): void
    {
        $this->stubCards();
        [$game] = $this->makeGame();
        $game->nameOptions = ['Poivre', 'Cannelle'];

        self::assertSame(['Poivre', 'Cannelle'], $game->getLocalizedNameOptions());
    }

    public function testLastCorrectNameLabelIsLocalized(): void
    {
        $this->stubLocalizedCards();
        [$game] = $this->makeGame();
        $game->lastCorrectName = 'Poivre';

        self::assertSame('Pepper', $game->getLastCorrectNameLabel());
    }

    public function testGetCurrentCardLocalizesTheAromaticGroupName(): void
    {
        $this->stubLocalizedCards();
        [$game] = $this->makeGame();
        $game->currentCardId = 42;
        $game->difficulty = 'easy';

        $card = $game->getCurrentCard();

        self::assertSame('Terpenes', $card['aromaticGroup']['name']);
        self::assertSame('#C00', $card['aromaticGroup']['color']);
    }

    #[DataProvider('provideAnswerCandidates')]
    public function testAnswerAcceptsLocaleAndFrenchNames(string $given, bool $expected): void
    {
        $this->stubLocalizedCards();
        $secret = $this->inProgressSecret('Poivre');
        $secret['correctId'] = 42;

        [$game] = $this->makeGame($secret);
        $this->academyManager->method('getRandomSpiceCard')
            ->willReturn(null);
        $this->academyManager->method('getChronoOptionsCount')
            ->willReturn(4);

        $game->answer($given);

        self::assertSame($expected, $game->lastAnswerCorrect);
        self::assertSame($expected ? 1 : 0, $game->correctCount);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideAnswerCandidates(): iterable
    {
        yield 'saisie dans la locale courante' => ['Pepper', true];
        yield 'saisie en français canonique' => ['Poivre', true];
        yield 'autre épice dans la locale courante' => ['Cinnamon', false];
        yield 'autre épice en français' => ['Cannelle', false];
    }

    public function testFrenchRenderIssuesNoEnrichmentQuery(): void
    {
        $repository = $this->createMock(SpicesRepository::class);
        $repository->expects(self::never())
            ->method('findEnrichedByIds');

        $game = $this->makeGameWithRealManager('fr', $repository);
        $game->currentCardId = 42;
        $game->nameOptions = ['Poivre', 'Cannelle'];
        $game->lastCorrectName = 'Poivre';

        self::assertSame(['Poivre', 'Cannelle'], $game->getLocalizedNameOptions());
        self::assertSame('Poivre', $game->getLastCorrectNameLabel());
        self::assertSame('Terpènes', $game->getCurrentCard()['aromaticGroup']['name']);
    }

    public function testEnglishRenderIssuesASingleEnrichmentQuery(): void
    {
        $repository = $this->createMock(SpicesRepository::class);
        $repository->expects(self::once())
            ->method('findEnrichedByIds')
            ->with([42, 7], 'en')
            ->willReturn([
                [
                    'id' => 42,
                    'name' => 'Pepper',
                    'slug' => 'pepper',
                    'file' => null,
                    'agId' => 1,
                    'color' => '#C00',
                    'groupName' => 'Terpenes',
                    'stId' => null,
                    'typeName' => null,
                ],
                [
                    'id' => 7,
                    'name' => 'Cinnamon',
                    'slug' => 'cinnamon',
                    'file' => null,
                    'agId' => 1,
                    'color' => '#C00',
                    'groupName' => 'Terpenes',
                    'stId' => null,
                    'typeName' => null,
                ],
            ]);

        $game = $this->makeGameWithRealManager('en', $repository);
        $game->currentCardId = 42;
        $game->nameOptions = ['Poivre', 'Cannelle'];
        $game->lastCorrectName = 'Poivre';

        self::assertSame(['Pepper', 'Cinnamon'], $game->getLocalizedNameOptions());
        self::assertSame('Pepper', $game->getLastCorrectNameLabel());
        self::assertSame('Terpenes', $game->getCurrentCard()['aromaticGroup']['name']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function localizedCards(): array
    {
        return [
            42 => [
                'id' => 42,
                'name' => 'Poivre',
                'file' => null,
                'description' => 'Baie piquante',
                'aromaticGroup' => [
                    'name' => 'Terpènes',
                    'color' => '#C00',
                ],
                'spicyType' => 'Épice',
                'mainCompounds' => [],
                'secondaryCompounds' => [],
                'alchemyFlavors' => [],
                'cookingTips' => [],
            ],
            7 => [
                'id' => 7,
                'name' => 'Cannelle',
                'file' => null,
                'description' => 'Écorce chaude',
                'aromaticGroup' => [
                    'name' => 'Terpènes',
                    'color' => '#C00',
                ],
                'spicyType' => 'Épice',
                'mainCompounds' => [],
                'secondaryCompounds' => [],
                'alchemyFlavors' => [],
                'cookingTips' => [],
            ],
        ];
    }

    private function stubCards(): void
    {
        $this->academyManager->method('getAllSpiceCards')
            ->willReturn(self::localizedCards());
    }

    private function stubLocalizedCards(): void
    {
        $this->stubCards();

        $translations = [
            42 => [
                'name' => 'Pepper',
                'groupName' => 'Terpenes',
            ],
            7 => [
                'name' => 'Cinnamon',
                'groupName' => 'Terpenes',
            ],
        ];

        $this->academyManager->method('localizeSpiceSummaries')
            ->willReturnCallback(static fn (array $summaries): array => array_map(
                static fn (array $summary): array => [
                    ...$summary,
                    'name' => $translations[$summary['id']]['name'] ?? $summary['name'],
                    'groupName' => $translations[$summary['id']]['groupName'] ?? $summary['groupName'],
                ],
                $summaries,
            ));
    }

    /**
     * @param SpicesRepository&MockObject $repository
     */
    private function makeGameWithRealManager(string $locale, SpicesRepository $repository): ChronoGame
    {
        $translator = new IdentityTranslator();
        $translator->setLocale($locale);

        $cache = new ArrayAdapter();
        $cache->get('academy.spice_cards', static fn (): array => self::localizedCards());

        $manager = new AcademyManager(
            $repository,
            $this->createStub(CompatibleSpiceFinder::class),
            $cache,
            $translator,
        );

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $game = new ChronoGame($manager, $this->sessionManager, $requestStack);
        $game->gameToken = self::TOKEN;
        $game->difficulty = 'easy';

        return $game;
    }
}
