<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Enum\GameDifficulty;
use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Match\CompatibleSpiceFinder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for the pure/stateless methods of AcademyManager.
 *
 * Methods that depend on the DB (getAllSpices, findCompatibleSpices, …)
 * are covered by Integration tests. Only pure logic is tested here.
 */
#[AllowMockObjectsWithoutExpectations]
class AcademyManagerTest extends TestCase
{
    private AcademyManager $manager;

    protected function setUp(): void
    {
        $this->manager = new AcademyManager(
            $this->createMock(SpicesRepository::class),
            $this->createMock(CompatibleSpiceFinder::class),
            new ArrayAdapter(),
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
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

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
}
