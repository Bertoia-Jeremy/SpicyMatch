<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Entity\Spices;
use App\Enum\GameDifficulty;
use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Match\CompatibleSpiceFinder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Translation\IdentityTranslator;

#[AllowMockObjectsWithoutExpectations]
class AcademyManagerTest extends TestCase
{
    private AcademyManager $manager;

    private SpicesRepository&MockObject $spicesRepo;

    private CompatibleSpiceFinder&MockObject $finder;

    protected function setUp(): void
    {
        $this->spicesRepo = $this->createMock(SpicesRepository::class);
        $this->finder = $this->createMock(CompatibleSpiceFinder::class);
        $this->manager = new AcademyManager(
            $this->spicesRepo,
            $this->finder,
            new ArrayAdapter(),
            new IdentityTranslator()
        );
    }

    public function testCountAvailableCluesReturnsZeroForEmptyCard(): void
    {
        self::assertSame(0, $this->manager->countAvailableClues([]));
    }

    public function testCountAvailableCluesCountsAllSixFields(): void
    {
        self::assertSame(6, $this->manager->countAvailableClues($this->makeFullSpiceCard()));
    }

    public function testCountAvailableCluesCountsOnlyPresentFields(): void
    {
        $card = [
            'alchemyFlavors' => ['Épicé'],
            'mainCompounds' => ['Thymol'],
        ];

        self::assertSame(2, $this->manager->countAvailableClues($card));
    }

    public function testCountAvailableCluesIgnoresEmptyArrayFields(): void
    {
        $card = [
            'alchemyFlavors' => [],
            'mainCompounds' => ['Thymol'],
        ];

        self::assertSame(1, $this->manager->countAvailableClues($card));
    }

    public function testCountAvailableCluesIgnoresEmptyStringDescription(): void
    {
        $card = [
            'description' => '',
            'mainCompounds' => ['Thymol'],
        ];

        self::assertSame(1, $this->manager->countAvailableClues($card));
    }

    public function testCountAvailableCluesIgnoresEmptyAromaticGroupName(): void
    {
        $card = [
            'aromaticGroup' => [
                'name' => '',
            ],
            'mainCompounds' => ['Thymol'],
        ];

        self::assertSame(1, $this->manager->countAvailableClues($card));
    }

    public function testBuildMaskHidesAllLettersWithNoGuesses(): void
    {
        self::assertSame('____', $this->manager->buildMask('Thym', []));
    }

    public function testBuildMaskRevealsGuessedLetters(): void
    {
        self::assertSame('Th__', $this->manager->buildMask('Thym', ['T', 'H']));
    }

    public function testBuildMaskPreservesSpaces(): void
    {
        self::assertSame('______ ____', $this->manager->buildMask('Poivre noir', []));
    }

    public function testBuildMaskPreservesHyphens(): void
    {
        self::assertSame('____-_____', $this->manager->buildMask('Miel-épice', []));
    }

    public function testBuildMaskPreservesApostrophes(): void
    {
        self::assertSame("_____ _'______", $this->manager->buildMask("Cumin d'Égypte", []));
    }

    public function testBuildMaskIsAccentInsensitiveForAccentedChars(): void
    {
        self::assertSame('É___e', $this->manager->buildMask('Épice', ['E']));
    }

    public function testBuildMaskFullyRevealedWordMatchesOriginal(): void
    {
        $name = 'Cumin';
        $guessed = ['C', 'U', 'M', 'I', 'N'];
        self::assertSame($name, $this->manager->buildMask($name, $guessed));
    }

    public function testLetterInWordReturnsTrueForPresentLetter(): void
    {
        self::assertTrue($this->manager->letterInWord('T', 'Thym'));
    }

    public function testLetterInWordReturnsFalseForAbsentLetter(): void
    {
        self::assertFalse($this->manager->letterInWord('Z', 'Thym'));
    }

    public function testLetterInWordIsCaseInsensitive(): void
    {
        self::assertTrue($this->manager->letterInWord('t', 'Thym'));
        self::assertTrue($this->manager->letterInWord('T', 'thym'));
    }

    public function testLetterInWordIsAccentInsensitiveOnWord(): void
    {
        self::assertTrue($this->manager->letterInWord('E', 'Épice'));
        self::assertTrue($this->manager->letterInWord('e', 'Épice'));
    }

    public function testLetterInWordIsAccentInsensitiveOnLetter(): void
    {
        self::assertTrue($this->manager->letterInWord('É', 'epicerie'));
    }

    public function testFilterByDifficultyReturnsEmptyForEmptyInput(): void
    {
        self::assertSame([], $this->manager->filterByDifficulty([], GameDifficulty::EASY));
    }

    public function testFilterByDifficultyEasyKeepsFiftyPercent(): void
    {
        $input = array_fill(0, 10, [
            'score' => 50,
        ]);
        self::assertCount(5, $this->manager->filterByDifficulty($input, GameDifficulty::EASY));
    }

    public function testFilterByDifficultyMediumKeepsSeventyPercent(): void
    {
        $input = array_fill(0, 10, [
            'score' => 50,
        ]);
        self::assertCount(7, $this->manager->filterByDifficulty($input, GameDifficulty::MEDIUM));
    }

    public function testFilterByDifficultyHardKeepsAll(): void
    {
        $input = array_fill(0, 10, [
            'score' => 50,
        ]);
        self::assertCount(10, $this->manager->filterByDifficulty($input, GameDifficulty::HARD));
    }

    public function testFilterByDifficultyPreservesOrderFromHighestScore(): void
    {
        $input = [
            [
                'score' => 90,
            ],
            [
                'score' => 70,
            ],
            [
                'score' => 50,
            ],
            [
                'score' => 30,
            ],
        ];

        $result = $this->manager->filterByDifficulty($input, GameDifficulty::EASY);

        self::assertCount(2, $result);
        self::assertSame(90, $result[0]['score']);
        self::assertSame(70, $result[1]['score']);
    }

    public function testGenerateGuessWhoCluesReturnsEmptyForEmptyCard(): void
    {
        self::assertSame([], $this->manager->generateGuessWhoClues([], GameDifficulty::MEDIUM));
    }

    public function testGenerateGuessWhoCluesMaxSixForEasy(): void
    {
        $clues = $this->manager->generateGuessWhoClues($this->makeFullSpiceCard(), GameDifficulty::EASY);
        self::assertCount(6, $clues);
    }

    public function testGenerateGuessWhoCluesMaxFourForMedium(): void
    {
        $clues = $this->manager->generateGuessWhoClues($this->makeFullSpiceCard(), GameDifficulty::MEDIUM);
        self::assertCount(4, $clues);
    }

    public function testGenerateGuessWhoCluesMaxThreeForHard(): void
    {
        $clues = $this->manager->generateGuessWhoClues($this->makeFullSpiceCard(), GameDifficulty::HARD);
        self::assertCount(3, $clues);
    }

    public function testGenerateGuessWhoCluesHaveRequiredKeys(): void
    {
        $clues = $this->manager->generateGuessWhoClues($this->makeFullSpiceCard(), GameDifficulty::HARD);

        foreach ($clues as $clue) {
            self::assertArrayHasKey('type', $clue);
            self::assertArrayHasKey('label', $clue);
            self::assertArrayHasKey('value', $clue);
        }
    }

    public function testGenerateGuessWhoCluesDoesNotExceedAvailableClues(): void
    {
        $card = [
            'alchemyFlavors' => ['Épicé'],
        ];

        $clues = $this->manager->generateGuessWhoClues($card, GameDifficulty::EASY);
        self::assertCount(1, $clues);
    }

    /**
     * @return iterable<string, array{GameDifficulty, int, array{int, int}}>
     */
    public static function difficultyBoundsProvider(): iterable
    {
        yield 'easy' => [GameDifficulty::EASY, 6, [6, 4], 6, 90, 4, [8, 12]];
        yield 'medium' => [GameDifficulty::MEDIUM, 4, [5, 3], 5, 75, 6, [12, 18]];
        yield 'hard' => [GameDifficulty::HARD, 3, [4, 2], 4, 60, 8, [16, 24]];
    }

    /**
     * @param array{int, int} $survivalCounts
     * @param array{int, int} $chronoThresholds
     */
    #[DataProvider('difficultyBoundsProvider')]
    public function testDifficultyBoundGetters(
        GameDifficulty $difficulty,
        int $maxClues,
        array $survivalCounts,
        int $hangmanErrors,
        int $chronoTime,
        int $chronoOptions,
        array $chronoThresholds,
    ): void {
        self::assertSame($maxClues, $this->manager->getGuessWhoMaxClues($difficulty));
        self::assertSame($survivalCounts, $this->manager->getSurvivalOptionCounts($difficulty));
        self::assertSame($hangmanErrors, $this->manager->getHangmanMaxErrors($difficulty));
        self::assertSame($chronoTime, $this->manager->getChronoTimeLimit($difficulty));
        self::assertSame($chronoOptions, $this->manager->getChronoOptionsCount($difficulty));
        self::assertSame($chronoThresholds, $this->manager->getChronoSpeedThresholds($difficulty));
    }

    public function testGenerateIntrusQuestionReturnsNullWithFewerThanFiveCandidates(): void
    {
        $spices = [];
        for ($i = 1; $i <= 4; ++$i) {
            $spice = $this->createMock(Spices::class);
            $spice->method('getId')
                ->willReturn($i);
            $spice->method('getAromaticGroups')
                ->willReturn(null);
            $spices[] = $spice;
        }

        $this->spicesRepo->method('findAllActive')
            ->willReturn($spices);

        $result = $this->manager->generateIntrusQuestion(GameDifficulty::EASY, []);

        self::assertNull($result);
    }

    public function testGenerateIntrusQuestionReturnsNullWhenAllCandidatesExcluded(): void
    {
        $spices = [];
        for ($i = 1; $i <= 5; ++$i) {
            $spice = $this->createMock(Spices::class);
            $spice->method('getId')
                ->willReturn($i);
            $spice->method('getAromaticGroups')
                ->willReturn(null);
            $spices[] = $spice;
        }

        $this->spicesRepo->method('findAllActive')
            ->willReturn($spices);

        $result = $this->manager->generateIntrusQuestion(GameDifficulty::EASY, [1, 2, 3, 4]);

        self::assertNull($result);
    }

    public function testGenerateIntrusQuestionPicksTheLowestScoringSurvivorAsIntruder(): void
    {
        $this->spicesRepo->method('findAllActive')
            ->willReturn($this->makeBaseSpices());
        $this->spicesRepo->method('findIncompatibleWith')
            ->willReturn([]);
        $this->finder->method('findCompatible')
            ->willReturn($this->makeScoredPool([90, 80, 70, 60]));

        $result = $this->manager->generateIntrusQuestion(GameDifficulty::EASY, [], false);

        self::assertNotNull($result);
        self::assertCount(4, $result['options']);
        self::assertCount(4, array_unique(array_column($result['options'], 'id')));
        self::assertSame(14, $result['correctAnswerId']);
    }

    public function testGenerateIntrusQuestionReturnsNullWhenEveryCompatibleSharesTheSameScore(): void
    {
        $this->spicesRepo->method('findAllActive')
            ->willReturn($this->makeBaseSpices());
        $this->spicesRepo->method('findIncompatibleWith')
            ->willReturn([]);
        $this->finder->method('findCompatible')
            ->willReturn($this->makeScoredPool([50, 50, 50, 50]));

        self::assertNull($this->manager->generateIntrusQuestion(GameDifficulty::HARD, [], false));
    }

    public function testGenerateIntrusQuestionLocalizesEveryOptionInNonFrenchLocale(): void
    {
        $intruder = $this->makeSpice(99);
        $intruder->setName('Poivre');

        $this->spicesRepo->method('findAllActive')
            ->willReturn($this->makeBaseSpices());
        $this->spicesRepo->method('findIncompatibleWith')
            ->willReturn([$intruder]);
        $this->spicesRepo->method('findEnrichedByIds')
            ->willReturn($this->makeEnrichedRows([
                11 => 'Cinnamon',
                12 => 'Nutmeg',
                13 => 'Clove',
                99 => 'Pepper',
            ]));
        $this->finder->method('findCompatible')
            ->willReturn($this->makeScoredPool([90, 80, 70, 60]));

        $translator = new IdentityTranslator();
        $translator->setLocale('en');

        $manager = new AcademyManager(
            $this->spicesRepo,
            $this->finder,
            new ArrayAdapter(),
            $translator,
        );

        $result = $manager->generateIntrusQuestion(GameDifficulty::EASY, [], false);

        self::assertNotNull($result);
        self::assertSame(99, $result['correctAnswerId']);

        $names = array_column($result['options'], 'name', 'id');
        self::assertSame('Pepper', $names[99]);
        self::assertSame(['Cinnamon', 'Nutmeg', 'Clove'], [$names[11], $names[12], $names[13]]);
    }

    public function testGenerateSurvivalOptionsLocalizesTrapsAndCompatiblesInOneBatch(): void
    {
        $firstTrap = $this->makeSpice(98);
        $firstTrap->setName('Poivre');
        $secondTrap = $this->makeSpice(99);
        $secondTrap->setName('Clou de girofle');

        $this->spicesRepo->method('findIncompatibleWith')
            ->willReturn([$firstTrap, $secondTrap]);
        $this->finder->method('findCompatible')
            ->willReturn($this->makeScoredPool([90, 80]));

        $capturedIds = [];
        $this->spicesRepo->expects(self::once())
            ->method('findEnrichedByIds')
            ->with(self::anything(), 'en')
            ->willReturnCallback(function (array $ids) use (&$capturedIds): array {
                $capturedIds = $ids;

                return $this->makeEnrichedRows([
                    11 => 'Cinnamon',
                    12 => 'Nutmeg',
                    98 => 'Pepper',
                    99 => 'Clove',
                ]);
            });

        $translator = new IdentityTranslator();
        $translator->setLocale('en');

        $manager = new AcademyManager(
            $this->spicesRepo,
            $this->finder,
            new ArrayAdapter(),
            $translator,
        );

        $options = $manager->generateSurvivalOptions($this->makeSpice(1), GameDifficulty::HARD);

        sort($capturedIds);
        self::assertSame([11, 12, 98, 99], $capturedIds);

        $names = array_column($options, 'name', 'id');
        self::assertSame(['Cinnamon', 'Nutmeg', 'Pepper', 'Clove'], [
            $names[11],
            $names[12],
            $names[98],
            $names[99],
        ]);

        $flags = array_column($options, 'isCompatible', 'id');
        self::assertSame([true, true, false, false], [$flags[11], $flags[12], $flags[98], $flags[99]]);
    }

    #[DataProvider('survivalCompositionProvider')]
    public function testGenerateSurvivalOptionsKeepsAMonotoneCompatibleRatio(
        GameDifficulty $difficulty,
        int $expectedOptions,
        int $expectedCompatibles,
    ): void {
        $traps = [];
        for ($id = 90; $id < 96; ++$id) {
            $traps[] = $this->makeSpice($id);
        }

        $this->spicesRepo->method('findIncompatibleWith')
            ->willReturn($traps);
        $this->finder->method('findCompatible')
            ->willReturn($this->makeScoredPool([90, 85, 80, 75, 70, 65, 60, 55]));

        $manager = new AcademyManager(
            $this->spicesRepo,
            $this->finder,
            new ArrayAdapter(),
            new IdentityTranslator(),
        );

        $options = $manager->generateSurvivalOptions($this->makeSpice(1), $difficulty);

        self::assertCount($expectedOptions, $options);
        self::assertSame(
            $expectedCompatibles,
            \count(array_filter($options, static fn (array $o): bool => $o['isCompatible'])),
        );
    }

    /**
     * @return iterable<string, array{GameDifficulty, int, int}>
     */
    public static function survivalCompositionProvider(): iterable
    {
        yield 'facile, 4 compatibles sur 6' => [GameDifficulty::EASY, 6, 4];
        yield 'moyen, 3 compatibles sur 5' => [GameDifficulty::MEDIUM, 5, 3];
        yield 'difficile, 2 compatibles sur 4' => [GameDifficulty::HARD, 4, 2];
    }

    public function testGenerateSurvivalOptionsIssuesNoEnrichmentQueryInFrench(): void
    {
        $trap = $this->makeSpice(99);
        $trap->setName('Poivre');

        $this->spicesRepo->method('findIncompatibleWith')
            ->willReturn([$trap]);
        $this->finder->method('findCompatible')
            ->willReturn($this->makeScoredPool([90, 80]));
        $this->spicesRepo->expects(self::never())
            ->method('findEnrichedByIds');

        $translator = new IdentityTranslator();
        $translator->setLocale('fr');

        $manager = new AcademyManager(
            $this->spicesRepo,
            $this->finder,
            new ArrayAdapter(),
            $translator,
        );

        $options = $manager->generateSurvivalOptions($this->makeSpice(1), GameDifficulty::HARD);

        $names = array_column($options, 'name', 'id');
        self::assertSame('Poivre', $names[99]);
        self::assertSame('Épice 11', $names[11]);
        self::assertSame('Épice 12', $names[12]);
    }

    public function testLocalizeSpiceSummariesRewritesNameAndGroupInOneBatch(): void
    {
        $capturedIds = [];
        $this->spicesRepo->expects(self::once())
            ->method('findEnrichedByIds')
            ->with(self::anything(), 'en')
            ->willReturnCallback(function (array $ids) use (&$capturedIds): array {
                $capturedIds = $ids;

                return [
                    [
                        'id' => 11,
                        'name' => 'Cinnamon',
                        'file' => null,
                        'color' => null,
                        'groupName' => 'Phenylpropanoids',
                    ],
                    [
                        'id' => 12,
                        'name' => 'Nutmeg',
                        'file' => null,
                        'color' => null,
                        'groupName' => null,
                    ],
                ];
            });

        $manager = $this->makeManagerForLocale('en');
        $summaries = $manager->localizeSpiceSummaries([
            $this->makeSummary(11, 'Cannelle', 'Phénylpropanoïdes'),
            $this->makeSummary(12, 'Muscade', 'Terpènes'),
        ]);

        self::assertSame([11, 12], $capturedIds);
        self::assertSame(['Cinnamon', 'Nutmeg'], array_column($summaries, 'name'));
        self::assertSame(['Phenylpropanoids', null], array_column($summaries, 'groupName'));
    }

    public function testLocalizeSpiceSummariesIssuesNoEnrichmentQueryInFrench(): void
    {
        $this->spicesRepo->expects(self::never())
            ->method('findEnrichedByIds');

        $manager = $this->makeManagerForLocale('fr');
        $summaries = $manager->localizeSpiceSummaries([
            $this->makeSummary(11, 'Cannelle', 'Phénylpropanoïdes'),
        ]);

        self::assertSame('Cannelle', $summaries[0]['name']);
        self::assertSame('Phénylpropanoïdes', $summaries[0]['groupName']);
    }

    private function makeManagerForLocale(string $locale): AcademyManager
    {
        $translator = new IdentityTranslator();
        $translator->setLocale($locale);

        return new AcademyManager($this->spicesRepo, $this->finder, new ArrayAdapter(), $translator);
    }

    /**
     * @return array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}
     */
    private function makeSummary(int $id, string $name, ?string $groupName): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'file' => null,
            'color' => null,
            'groupName' => $groupName,
        ];
    }

    /**
     * @return list<Spices>
     */
    private function makeBaseSpices(): array
    {
        $spices = [];
        for ($id = 1; $id <= 6; ++$id) {
            $spices[] = $this->makeSpice($id);
        }

        return $spices;
    }

    private function makeSpice(int $id): Spices
    {
        $spice = new Spices();
        new \ReflectionProperty(Spices::class, 'id')->setValue($spice, $id);

        return $spice;
    }

    /**
     * @param list<int> $scores
     * @return list<array<string, mixed>>
     */
    private function makeScoredPool(array $scores, int $firstId = 11): array
    {
        $pool = [];
        foreach ($scores as $offset => $score) {
            $pool[] = [
                'id' => $firstId + $offset,
                'name' => 'Épice ' . ($firstId + $offset),
                'score' => $score,
                'file' => null,
                'agId' => null,
                'color' => null,
                'groupName' => null,
                'stId' => null,
                'typeName' => null,
            ];
        }

        return $pool;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeFullSpiceCard(): array
    {
        return [
            'description' => 'A fragrant spice used in Mediterranean cooking.',
            'alchemyFlavors' => ['Épicé', 'Chaud', 'Terreux'],
            'mainCompounds' => ['Thymol', 'Carvacrol'],
            'spicyType' => 'Herbacé',
            'aromaticGroup' => [
                'name' => 'Monoterpènes',
            ],
            'cookingTips' => [[
                'title' => 'Infuser hors du feu',
            ]],
        ];
    }

    /**
     * @param array<int, string> $namesById
     * @return list<array<string, mixed>>
     */
    private function makeEnrichedRows(array $namesById): array
    {
        $rows = [];
        foreach ($namesById as $id => $name) {
            $rows[] = [
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

        return $rows;
    }
}
