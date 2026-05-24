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

    // ── Cas frontières ─────────────────────────────────────────────────────────────

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
        // Aucun composé en commun → Tanimoto = 0
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
        // Profils identiques → Tanimoto = 1
        $profile = [
            1 => 10.0,
            2 => 5.0,
            3 => 2.0,
        ];

        self::assertEqualsWithDelta(1.0, $this->scorer->score($profile, $profile), 1e-9);
    }

    // ── Cas partiels ───────────────────────────────────────────────────────────────

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

        // min(10,10) + min(5,0) + min(0,8) = 10
        // max(10,10) + max(5,0) + max(0,8) = 10 + 5 + 8 = 23
        $expected = 10.0 / 23.0;

        self::assertEqualsWithDelta($expected, $this->scorer->score($candidate, $mortar), 1e-9);
        // Symétrie : score(a, b) == score(b, a)
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

        // min(4,8) / max(4,8) = 4/8 = 0.5
        self::assertEqualsWithDelta(0.5, $this->scorer->score($candidate, $mortar), 1e-9);
    }

    public function testScoreAsIntFloor(): void
    {
        // score = 0.876 → floor(100 * 0.876) = 87
        $candidate = [
            1 => 8.76,
        ];
        $mortar = [
            1 => 10.0,
        ];
        // min=8.76, max=10.0 → 0.876
        self::assertSame(87, $this->scorer->scoreAsInt($candidate, $mortar));
    }

    // ── Monotonie ─────────────────────────────────────────────────────────────────

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

    // ── Valeurs de référence extraites de MatchPipelineTest ──────────────────────

    public function testScoreFromPipelineSpecCandidate10(): void
    {
        // Référence : commentaire MatchPipelineTest::testOavModeUsesOavVetoAndScores
        // min(90,100)+min(40,50) / max(90,100)+max(40,50) = 130/150 ≈ 0.8666 → floor = 86
        // (round donnerait 87 — vérifie que floor est utilisé, pas round)
        self::assertSame(86, $this->scorer->scoreAsInt([
            1 => 90.0,
            2 => 40.0,
        ], [
            1 => 100.0,
            2 => 50.0,
        ],));
    }

    public function testScoreFromPipelineSpecCandidate11(): void
    {
        // Référence : candidat avec un seul composé en commun
        // min(10,100) + 0 / max(10,100) + max(0,50) = 10/150 ≈ 0.0666 → floor = 6
        self::assertSame(6, $this->scorer->scoreAsInt([
            1 => 10.0,
        ], [
            1 => 100.0,
            2 => 50.0,
        ],));
    }

    // ── Bornes ────────────────────────────────────────────────────────────────────

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
