<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Service\Match\OavTanimotoScorer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OavTanimotoScorer::class)]
final class OavTanimotoScorerTest extends TestCase
{
    private OavTanimotoScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new OavTanimotoScorer();
    }

    public function testScoreEmptyCandidateReturnsZero(): void
    {
        self::assertSame(0.0, $this->scorer->score([], [
            1 => 5.0,
        ]));
    }

    public function testScoreEmptyMortarReturnsZero(): void
    {
        self::assertSame(0.0, $this->scorer->score([
            1 => 5.0,
        ], []));
    }

    public function testScoreBothEmptyReturnsZero(): void
    {
        self::assertSame(0.0, $this->scorer->score([], []));
    }

    public function testScoreDisjointSetsReturnsZero(): void
    {
        $candidate = [
            1 => 10.0,
            2 => 5.0,
        ];
        $mortar = [
            3 => 8.0,
            4 => 3.0,
        ];

        self::assertSame(0.0, $this->scorer->score($candidate, $mortar));
    }

    public function testScoreIdenticalProfileReturnsOne(): void
    {
        $profile = [
            1 => 10.0,
            2 => 5.0,
            3 => 2.0,
        ];

        self::assertEqualsWithDelta(1.0, $this->scorer->score($profile, $profile), 1e-9);
    }

    public function testScorePartialOverlapSymmetric(): void
    {
        $candidate = [
            1 => 10.0,
            2 => 5.0,
        ];
        $mortar = [
            1 => 10.0,
            3 => 8.0,
        ];

        $expected = log(10) / (log(10) + log(5) + log(8));

        self::assertEqualsWithDelta($expected, $this->scorer->score($candidate, $mortar), 1e-9);
        self::assertEqualsWithDelta($expected, $this->scorer->score($mortar, $candidate), 1e-9);
    }

    public function testScoreSingleSharedCompound(): void
    {
        $candidate = [
            1 => 4.0,
        ];
        $mortar = [
            1 => 8.0,
        ];

        self::assertEqualsWithDelta(log(4) / log(8), $this->scorer->score($candidate, $mortar), 1e-9);
    }

    public function testScoreAsIntFloor(): void
    {
        $candidate = [
            1 => 8.76,
        ];
        $mortar = [
            1 => 10.0,
        ];
        self::assertSame(94, $this->scorer->scoreAsInt($candidate, $mortar));
    }

    public function testScoreIncreasesWithMoreSharedCompounds(): void
    {
        $mortar = [
            1 => 10.0,
            2 => 8.0,
            3 => 6.0,
        ];

        $candidateOne = [
            1 => 10.0,
        ];
        $candidateTwo = [
            1 => 10.0,
            2 => 8.0,
        ];
        $candidateAll = [
            1 => 10.0,
            2 => 8.0,
            3 => 6.0,
        ];

        $s1 = $this->scorer->score($candidateOne, $mortar);
        $s2 = $this->scorer->score($candidateTwo, $mortar);
        $s3 = $this->scorer->score($candidateAll, $mortar);

        self::assertLessThan($s2, $s1, 'score(1 composé) < score(2 composés)');
        self::assertLessThan($s3, $s2, 'score(2 composés) < score(3 composés)');
        self::assertEqualsWithDelta(1.0, $s3, 1e-9, 'profils identiques → 1.0');
    }

    public function testScoreFromPipelineSpecCandidate10(): void
    {
        self::assertSame(96, $this->scorer->scoreAsInt([
            1 => 90.0,
            2 => 40.0,
        ], [
            1 => 100.0,
            2 => 50.0,
        ], ));
    }

    public function testScoreFromPipelineSpecCandidate11(): void
    {
        self::assertSame(27, $this->scorer->scoreAsInt([
            1 => 10.0,
        ], [
            1 => 100.0,
            2 => 50.0,
        ], ));
    }

    #[DataProvider('randomProfilesProvider')]
    public function testScoreAlwaysBetweenZeroAndOne(array $candidate, array $mortar): void
    {
        $score = $this->scorer->score($candidate, $mortar);
        self::assertGreaterThanOrEqual(0.0, $score);
        self::assertLessThanOrEqual(1.0, $score);
    }

    /**
     * @return array<string, array{array<int,float>, array<int,float>}>
     */
    public static function randomProfilesProvider(): array
    {
        return [
            'fully overlapping' => [[
                1 => 5.0,
                2 => 3.0,
            ], [
                1 => 5.0,
                2 => 3.0,
            ]],
            'partial overlap' => [[
                1 => 10.0,
                2 => 1.0,
            ], [
                1 => 2.0,
                3 => 8.0,
            ]],
            'no overlap' => [[
                1 => 7.0,
            ], [
                2 => 3.0,
            ]],
            'high OAV candidate' => [[
                1 => 1000.0,
                2 => 500.0,
            ], [
                1 => 2.0,
                2 => 1.5,
            ]],
            'single compound each' => [[
                1 => 1.5,
            ], [
                1 => 3.0,
            ]],
        ];
    }
}
