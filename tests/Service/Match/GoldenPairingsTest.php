<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Service\Match\OavTanimotoScorer;
use PHPUnit\Framework\TestCase;

/**
 * /**
 * Ancres de régression — verrouillent le comportement du scoring sur des accords
 * aromatiques connus (profils OAV tirés des fixtures, matrice air).
 * Scorer pur, sans base. Pendant chimie-réelle dans la suite Integration.
 *
 * Composés : 3=Anéthol, 4=Estragole, 5=Linalol, 6=Terpinèn-4-ol, 8=D-Limonène.
 */
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
        // Anis étoilé + Fenouil : tous deux dominés par l'anéthol + estragole → accord fort.
        $score = $this->scorer->score(self::ANIS_ETOILE, self::FENOUIL);

        self::assertGreaterThan(0.6, $score, 'Anis + Fenouil devraient être fortement compatibles');
    }

    public function testAniseBeatsCarviAgainstAniseMortar(): void
    {
        // Contre un profil anisé (fenouil), un autre anisé pur (anis étoilé) doit scorer
        // strictement mieux que le carvi (qui diverge : linalol + limonène marqués).
        $scoreAnise = $this->scorer->score(self::ANIS_ETOILE, self::FENOUIL);
        $scoreCarvi = $this->scorer->score(self::CARVI, self::FENOUIL);

        self::assertGreaterThan($scoreCarvi, $scoreAnise, 'Anis pur > Carvi face à un mortier anisé');
    }

    public function testCarviIsCompatibleButNotZero(): void
    {
        // Verrou anti-régression du fix « scores 0% » : le carvi partage l'anéthol,
        // le linalol et le limonène avec un mortier anisé → score significatif (> 0),
        // PAS écrasé à 0 comme avec l'ancien Tanimoto linéaire.
        $score = $this->scorer->scoreAsInt(self::CARVI, self::FENOUIL);

        self::assertGreaterThan(0, $score, 'Carvi ne doit pas être à 0 % (régression log-compression)');
        self::assertLessThan(100, $score);
    }

    public function testDivergentProfileScoresLowerThanRelative(): void
    {
        // Un profil pauvre/monoterpène (poivre-like, limonène seul) doit scorer plus bas
        // contre le fenouil qu'un vrai parent anisé (anis étoilé).
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
        // Le scoring doit être symétrique (propriété Tanimoto) sur des cas réels.
        self::assertEqualsWithDelta(
            $this->scorer->score(self::ANIS_ETOILE, self::CARVI),
            $this->scorer->score(self::CARVI, self::ANIS_ETOILE),
            1e-9,
        );
    }
}
