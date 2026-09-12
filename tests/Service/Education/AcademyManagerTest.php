<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Entity\Spices;
use App\Enum\GameDifficulty;
use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Match\CompatibleSpiceFinder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * Unit tests for the pure/stateless methods of AcademyManager.
 *
 * Methods that depend on the DB (getAllSpices, findCompatibleSpices, …)
 * are covered by Integration tests. Only pure logic is tested here.
 * generateIntrusQuestion() guard cases are tested here (null returns).
 */
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

    // ──────────────────────────────────────────────────────────────────────────
    // countAvailableClues
    // ──────────────────────────────────────────────────────────────────────────

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
            'alchemyFlavors' => [],      // empty array → not counted
            'mainCompounds' => ['Thymol'],
        ];

        self::assertSame(1, $this->manager->countAvailableClues($card));
    }

    public function testCountAvailableCluesIgnoresEmptyStringDescription(): void
    {
        $card = [
            'description' => '',         // empty string → not counted
            'mainCompounds' => ['Thymol'],
        ];

        self::assertSame(1, $this->manager->countAvailableClues($card));
    }

    public function testCountAvailableCluesIgnoresEmptyAromaticGroupName(): void
    {
        $card = [
            'aromaticGroup' => [
                'name' => '',
            ], // empty → not counted
            'mainCompounds' => ['Thymol'],
        ];

        self::assertSame(1, $this->manager->countAvailableClues($card));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // buildMask
    // ──────────────────────────────────────────────────────────────────────────

    public function testBuildMaskHidesAllLettersWithNoGuesses(): void
    {
        self::assertSame('____', $this->manager->buildMask('Thym', []));
    }

    public function testBuildMaskRevealsGuessedLetters(): void
    {
        // T and H guessed → Th revealed, y and m hidden
        self::assertSame('Th__', $this->manager->buildMask('Thym', ['T', 'H']));
    }

    public function testBuildMaskPreservesSpaces(): void
    {
        self::assertSame('______ ____', $this->manager->buildMask('Poivre noir', []));
    }

    public function testBuildMaskPreservesHyphens(): void
    {
        // "Miel-épice" → ____-_____
        self::assertSame('____-_____', $this->manager->buildMask('Miel-épice', []));
    }

    public function testBuildMaskPreservesApostrophes(): void
    {
        // "Cumin d'Égypte" → _____ _'______
        self::assertSame("_____ _'______", $this->manager->buildMask("Cumin d'Égypte", []));
    }

    public function testBuildMaskIsAccentInsensitiveForAccentedChars(): void
    {
        // Guessing 'E' should reveal both 'É' (accented) and 'e' (plain)
        self::assertSame('É___e', $this->manager->buildMask('Épice', ['E']));
    }

    public function testBuildMaskFullyRevealedWordMatchesOriginal(): void
    {
        $name = 'Cumin';
        $guessed = ['C', 'U', 'M', 'I', 'N'];
        self::assertSame($name, $this->manager->buildMask($name, $guessed));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // letterInWord
    // ──────────────────────────────────────────────────────────────────────────

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
        // 'E' matches 'é' in 'Épice'
        self::assertTrue($this->manager->letterInWord('E', 'Épice'));
        self::assertTrue($this->manager->letterInWord('e', 'Épice'));
    }

    public function testLetterInWordIsAccentInsensitiveOnLetter(): void
    {
        // Accented guess 'É' should match plain 'e'
        self::assertTrue($this->manager->letterInWord('É', 'epicerie'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // filterByDifficulty
    // ──────────────────────────────────────────────────────────────────────────

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
        // Input is sorted desc by score (as returned by findCompatible)
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

        // EASY → ceil(4 * 0.5) = 2 → keeps the top 2
        self::assertCount(2, $result);
        self::assertSame(90, $result[0]['score']);
        self::assertSame(70, $result[1]['score']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // generateGuessWhoClues
    // ──────────────────────────────────────────────────────────────────────────

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
        // Card with only 1 clue — even EASY should return at most 1
        $card = [
            'alchemyFlavors' => ['Épicé'],
        ];

        $clues = $this->manager->generateGuessWhoClues($card, GameDifficulty::EASY);
        self::assertCount(1, $clues);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Difficulty getter methods
    // ──────────────────────────────────────────────────────────────────────────

    public function testGetGuessWhoOptionsCount(): void
    {
        self::assertSame(2, $this->manager->getGuessWhoOptionsCount(GameDifficulty::EASY));
        self::assertSame(3, $this->manager->getGuessWhoOptionsCount(GameDifficulty::MEDIUM));
        self::assertSame(4, $this->manager->getGuessWhoOptionsCount(GameDifficulty::HARD));
    }

    public function testGetHangmanMaxErrors(): void
    {
        self::assertSame(6, $this->manager->getHangmanMaxErrors(GameDifficulty::EASY));
        self::assertSame(5, $this->manager->getHangmanMaxErrors(GameDifficulty::MEDIUM));
        self::assertSame(4, $this->manager->getHangmanMaxErrors(GameDifficulty::HARD));
    }

    public function testGetChronoTimeLimit(): void
    {
        self::assertSame(90, $this->manager->getChronoTimeLimit(GameDifficulty::EASY));
        self::assertSame(75, $this->manager->getChronoTimeLimit(GameDifficulty::MEDIUM));
        self::assertSame(60, $this->manager->getChronoTimeLimit(GameDifficulty::HARD));
    }

    public function testGetChronoOptionsCount(): void
    {
        self::assertSame(4, $this->manager->getChronoOptionsCount(GameDifficulty::EASY));
        self::assertSame(6, $this->manager->getChronoOptionsCount(GameDifficulty::MEDIUM));
        self::assertSame(8, $this->manager->getChronoOptionsCount(GameDifficulty::HARD));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // generateIntrusQuestion — guard cases
    // ──────────────────────────────────────────────────────────────────────────

    public function testGenerateIntrusQuestionReturnsNullWithFewerThanFiveCandidates(): void
    {
        // 4 spices total → candidates < 5 → null (both branches)
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
        // 5 spices, but 4 excluded → 1 remaining → count < 5 → null
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
        $trap = $this->makeSpice(99);
        $trap->setName('Poivre');

        $this->spicesRepo->method('findIncompatibleWith')
            ->willReturn([$trap]);
        $this->finder->method('findCompatible')
            ->willReturn($this->makeScoredPool([90, 80, 70]));

        $capturedIds = [];
        $this->spicesRepo->expects(self::once())
            ->method('findEnrichedByIds')
            ->with(self::anything(), 'en')
            ->willReturnCallback(function (array $ids) use (&$capturedIds): array {
                $capturedIds = $ids;

                return $this->makeEnrichedRows([
                    11 => 'Cinnamon',
                    12 => 'Nutmeg',
                    13 => 'Clove',
                    99 => 'Pepper',
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
        self::assertSame([11, 12, 13, 99], $capturedIds);

        $names = array_column($options, 'name', 'id');
        self::assertSame('Pepper', $names[99]);
        self::assertSame(['Cinnamon', 'Nutmeg', 'Clove'], [$names[11], $names[12], $names[13]]);

        $flags = array_column($options, 'isCompatible', 'id');
        self::assertFalse($flags[99]);
        self::assertSame([true, true, true], [$flags[11], $flags[12], $flags[13]]);
    }

    public function testGenerateSurvivalOptionsIssuesNoEnrichmentQueryInFrench(): void
    {
        $trap = $this->makeSpice(99);
        $trap->setName('Poivre');

        $this->spicesRepo->method('findIncompatibleWith')
            ->willReturn([$trap]);
        $this->finder->method('findCompatible')
            ->willReturn($this->makeScoredPool([90, 80, 70]));
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
     *
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
     *
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
