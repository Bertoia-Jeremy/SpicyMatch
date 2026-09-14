<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Service\Match\OavTanimotoScorer;
use PHPUnit\Framework\TestCase;

final class GoldenPairingsTest extends TestCase
{
    private OavTanimotoScorer $scorer;

    /**
     * @var array<int, float>
     */
    private const FENOUIL = [
        3 => 50_000_000.0,
        4 => 100_000.0,
        5 => 50_000.0,
        8 => 2_000.0,
    ];

    /**
     * @var array<int, float>
     */
    private const ANIS_ETOILE = [
        3 => 80_000_000.0,
        4 => 60_000.0,
        8 => 1_000.0,
    ];

    /**
     * @var array<int, float>
     */
    private const CARVI = [
        3 => 1_500_000.0,
        5 => 50_000.0,
        8 => 30_000.0,
        6 => 2_000.0,
    ];

    /**
     * @var array<int, float>
     */
    private const POIVRE_LIKE = [
        8 => 5_000.0,
    ];

    protected function setUp(): void
    {
        $this->scorer = new OavTanimotoScorer();
    }

    public function testAniseFamilyPairsStrongly(): void
    {
        $score = $this->scorer->score(self::ANIS_ETOILE, self::FENOUIL);

        self::assertGreaterThan(0.6, $score, 'Anis + Fenouil devraient être fortement compatibles');
    }

    public function testAniseBeatsCarviAgainstAniseMortar(): void
    {
        $scoreAnise = $this->scorer->score(self::ANIS_ETOILE, self::FENOUIL);
        $scoreCarvi = $this->scorer->score(self::CARVI, self::FENOUIL);

        self::assertGreaterThan($scoreCarvi, $scoreAnise, 'Anis pur > Carvi face à un mortier anisé');
    }

    public function testCarviIsCompatibleButNotZero(): void
    {
        $score = $this->scorer->scoreAsInt(self::CARVI, self::FENOUIL);

        self::assertGreaterThan(0, $score, 'Carvi ne doit pas être à 0 % (régression log-compression)');
        self::assertLessThan(100, $score);
    }

    public function testDivergentProfileScoresLowerThanRelative(): void
    {
        $scorePoivre = $this->scorer->score(self::POIVRE_LIKE, self::FENOUIL);
        $scoreAnise = $this->scorer->score(self::ANIS_ETOILE, self::FENOUIL);

        self::assertLessThan($scoreAnise, $scorePoivre, 'Profil divergent < parent anisé');
    }

    public function testIdenticalProfileIsPerfect(): void
    {
        self::assertSame(100, $this->scorer->scoreAsInt(self::FENOUIL, self::FENOUIL));
    }

    public function testSymmetryHoldsOnGoldenPairs(): void
    {
        self::assertEqualsWithDelta(
            $this->scorer->score(self::ANIS_ETOILE, self::CARVI),
            $this->scorer->score(self::CARVI, self::ANIS_ETOILE),
            1e-9,
        );
    }
}
