<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Enum\GameDifficulty;
use App\Service\Education\SkillAssessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SkillAssessorTest extends TestCase
{
    /**
     * @param list<float> $accuracies
     */
    #[DataProvider('silentProvider')]
    public function testAssessStaysSilent(GameDifficulty $current, array $accuracies): void
    {
        self::assertNull(new SkillAssessor()->assess($current, $accuracies));
    }

    /**
     * @return iterable<string, array{GameDifficulty, list<float>}>
     */
    public static function silentProvider(): iterable
    {
        yield 'aucune partie' => [GameDifficulty::EASY, []];
        yield 'moins de trois parties, même parfaites' => [GameDifficulty::EASY, [100.0, 100.0]];
        yield 'moyenne sous le seuil de promotion' => [GameDifficulty::EASY, [80.0, 80.0, 70.0]];
        yield 'moyenne haute mais une partie ratée casse la stabilité' => [
            GameDifficulty::EASY,
            [100.0, 100.0, 100.0, 100.0, 40.0],
        ];
        yield 'zone neutre, ni promotion ni rétrogradation' => [GameDifficulty::MEDIUM, [60.0, 55.0, 65.0]];
        yield 'maîtrise totale déjà en difficile' => [GameDifficulty::HARD, [100.0, 100.0, 100.0]];
        yield 'échec total déjà en facile' => [GameDifficulty::EASY, [0.0, 0.0, 0.0]];
        yield 'moyenne basse mais une partie réussie casse la stabilité' => [
            GameDifficulty::HARD,
            [0.0, 0.0, 0.0, 0.0, 100.0],
        ];
    }

    /**
     * @param list<float> $accuracies
     */
    #[DataProvider('suggestionProvider')]
    public function testAssessSuggestsANeighbouringDifficulty(
        GameDifficulty $current,
        array $accuracies,
        GameDifficulty $expected,
        float $expectedWinrate,
        int $expectedSamples,
        bool $expectedPromotion,
    ): void {
        $assessment = new SkillAssessor()
            ->assess($current, $accuracies);

        self::assertNotNull($assessment);
        self::assertSame($current, $assessment->current);
        self::assertSame($expected, $assessment->suggested);
        self::assertSame($expectedWinrate, $assessment->winrate);
        self::assertSame($expectedSamples, $assessment->samples);
        self::assertSame($expectedPromotion, $assessment->isPromotion());
    }

    /**
     * @return iterable<string, array{GameDifficulty, list<float>, GameDifficulty, float, int, bool}>
     */
    public static function suggestionProvider(): iterable
    {
        yield 'facile maîtrisé au minimum de parties' => [
            GameDifficulty::EASY,
            [100.0, 80.0, 60.0],
            GameDifficulty::MEDIUM,
            80.0,
            3,
            true,
        ];

        yield 'moyen maîtrisé sur fenêtre pleine' => [
            GameDifficulty::MEDIUM,
            [100.0, 100.0, 80.0, 80.0, 60.0],
            GameDifficulty::HARD,
            84.0,
            5,
            true,
        ];

        yield 'difficile subi, redescente proposée' => [
            GameDifficulty::HARD,
            [20.0, 40.0, 60.0],
            GameDifficulty::MEDIUM,
            40.0,
            3,
            false,
        ];

        yield 'moyen subi, redescente proposée' => [
            GameDifficulty::MEDIUM,
            [0.0, 20.0, 20.0, 40.0],
            GameDifficulty::EASY,
            20.0,
            4,
            false,
        ];
    }

    public function testAssessOnlyReadsTheMostRecentWindow(): void
    {
        $accuracies = [100.0, 100.0, 100.0, 100.0, 100.0, 0.0, 0.0, 0.0];

        $assessment = new SkillAssessor()
            ->assess(GameDifficulty::EASY, $accuracies);

        self::assertNotNull($assessment);
        self::assertSame(SkillAssessor::WINDOW, $assessment->samples);
        self::assertSame(100.0, $assessment->winrate);
    }
}
