<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Entity\AromaticCompound;
use App\Entity\CompoundPhysical;
use App\Enum\OdtMatrix;
use App\Service\Match\OavPartitionCalculator;
use App\ValueObject\Match\CulinaryContext;
use PHPUnit\Framework\TestCase;

final class OavPartitionCalculatorTest extends TestCase
{
    private OavPartitionCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new OavPartitionCalculator();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makePhysical(?float $logP = null, ?int $bp = null): CompoundPhysical
    {
        $compound = (new AromaticCompound())->setName('Test');
        $physical = new CompoundPhysical($compound);

        if (null !== $logP) {
            $physical->setLogP($logP);
        }
        if (null !== $bp) {
            $physical->setBoilingPointCelsius($bp);
        }

        return $physical;
    }

    // ── Garde-fous ─────────────────────────────────────────────────────────────

    public function testReturnsNullWhenOdtIsZero(): void
    {
        $oav = $this->calc->effectiveOav($this->makePhysical(2.0, 200), 100.0, 0.0, new CulinaryContext());
        self::assertNull($oav);
    }

    public function testReturnsNullWhenOdtIsNegative(): void
    {
        $oav = $this->calc->effectiveOav($this->makePhysical(2.0, 200), 100.0, -0.1, new CulinaryContext());
        self::assertNull($oav);
    }

    public function testReturnsZeroWhenConcentrationIsZero(): void
    {
        $oav = $this->calc->effectiveOav($this->makePhysical(2.0, 200), 0.0, 1.0, new CulinaryContext());
        self::assertSame(0.0, $oav);
    }

    // ── Mode dégradé : pas de physique ────────────────────────────────────────

    public function testFallbackToRawOavWhenPhysicalIsNull(): void
    {
        // Pas de CompoundPhysical → OAV brut = concentration / ODT
        $oav = $this->calc->effectiveOav(null, 1000.0, 5.0, new CulinaryContext());
        self::assertSame(200.0, $oav);
    }

    public function testFallbackToRawOavWhenLogPMissing(): void
    {
        // CompoundPhysical présent mais sans logP → pas de partition, OAV brut
        $physical = $this->makePhysical(logP: null);
        $oav = $this->calc->effectiveOav($physical, 1000.0, 5.0, new CulinaryContext(OdtMatrix::WATER));
        self::assertSame(200.0, $oav);
    }

    // ── Nernst : matrices pures ───────────────────────────────────────────────

    public function testPureWaterMixGivesRawOavInWaterMatrix(): void
    {
        // φ_water = 1, φ_oil = 0 → denom = K_ow × 0 + 1 = 1 → C_water = C_total
        $oav = $this->calc->effectiveOav(
            $this->makePhysical(logP: 3.0),
            concentrationPpm: 500.0,
            odtPpm: 5.0,
            ctx: new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.0, waterRatio: 1.0),
        );
        self::assertSame(100.0, $oav);
    }

    public function testPureOilMixGivesRawOavInOilMatrix(): void
    {
        // φ_oil = 1, φ_water = 0, K_ow = 1000 → denom = 1000 → C_oil = C × 1000 / 1000 = C
        $oav = $this->calc->effectiveOav(
            $this->makePhysical(logP: 3.0),
            concentrationPpm: 500.0,
            odtPpm: 5.0,
            ctx: new CulinaryContext(OdtMatrix::OIL, fatRatio: 1.0, waterRatio: 0.0),
        );
        self::assertSame(100.0, $oav);
    }

    public function testAirMatrixIgnoresPartition(): void
    {
        // matrix=AIR : on retourne la concentration totale (pas de phase solvant)
        $oav = $this->calc->effectiveOav(
            $this->makePhysical(logP: 4.0),
            concentrationPpm: 1000.0,
            odtPpm: 0.005,
            ctx: new CulinaryContext(OdtMatrix::AIR),
        );
        self::assertSame(1000.0 / 0.005, $oav);
    }

    // ── Nernst : mélanges biphasiques ─────────────────────────────────────────

    public function testHydrophobicCompoundConcentratesInOilPhase(): void
    {
        // K_ow = 100 (logP = 2). Mix 50/50.
        // denom = 100 × 0.5 + 0.5 = 50.5
        // C_water = 1000 / 50.5 ≈ 19.8
        // C_oil   = 1000 × 100 / 50.5 ≈ 1980.2
        $physical = $this->makePhysical(logP: 2.0);
        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.5, waterRatio: 0.5);

        $oavWater = $this->calc->effectiveOav($physical, 1000.0, 1.0, $ctx);
        self::assertEqualsWithDelta(19.802, $oavWater, 0.01);

        $oavOil = $this->calc->effectiveOav(
            $physical,
            1000.0,
            1.0,
            new CulinaryContext(OdtMatrix::OIL, fatRatio: 0.5, waterRatio: 0.5),
        );
        self::assertEqualsWithDelta(1980.198, $oavOil, 0.01);
    }

    public function testHydrophilicCompoundStaysInWaterPhase(): void
    {
        // logP négatif → K_ow < 1 → compose préfère l'eau
        // logP = -1 → K_ow = 0.1
        // denom = 0.1 × 0.5 + 0.5 = 0.55
        // C_water = 100 / 0.55 ≈ 181.8 (concentré en eau)
        // C_oil   = 100 × 0.1 / 0.55 ≈ 18.2
        $physical = $this->makePhysical(logP: -1.0);
        $ctxWater = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.5, waterRatio: 0.5);
        $ctxOil = new CulinaryContext(OdtMatrix::OIL, fatRatio: 0.5, waterRatio: 0.5);

        $oavWater = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctxWater);
        $oavOil = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctxOil);

        self::assertGreaterThan($oavOil, $oavWater, 'Composé hydrophile doit se concentrer en eau');
        self::assertEqualsWithDelta(181.818, $oavWater, 0.01);
        self::assertEqualsWithDelta(18.181, $oavOil, 0.01);
    }

    // ── Décroissance temporelle ───────────────────────────────────────────────

    public function testNoDecayWhenCookingTimeIsZero(): void
    {
        $physical = $this->makePhysical(logP: 2.0, bp: 200);
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 0, temperatureCelsius: 100);

        $oav = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctx);

        // Sans cuisson : matrix WATER + ratios par défaut (0/1) → denom = K_ow×0 + 1 = 1 → C_water = 100
        self::assertSame(100.0, $oav);
    }

    public function testNoDecayBelowInertThreshold(): void
    {
        // T = 49 °C < T_inert (50) → pas de décroissance même avec cuisson 60 min
        $physical = $this->makePhysical(logP: 0.0, bp: 100);
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 60, temperatureCelsius: 49);

        $oav = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctx);

        // logP=0 → K_ow=1, eau pure → C = 100
        self::assertSame(100.0, $oav);
    }

    public function testNoDecayWhenBoilingPointMissing(): void
    {
        // Pas de bp → pas de modèle de décroissance possible
        $physical = $this->makePhysical(logP: 0.0, bp: null);
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 60, temperatureCelsius: 100);

        $oav = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctx);
        self::assertSame(100.0, $oav);
    }

    public function testFullDecayAtBoilingPoint(): void
    {
        // T = bp → progress = 1 → k = K_AT_BOILING = 0.1
        // C(30 min) = 100 × exp(-0.1 × 30) = 100 × exp(-3) ≈ 4.98
        $physical = $this->makePhysical(logP: 0.0, bp: 100);
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 30, temperatureCelsius: 100);

        $oav = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctx);
        self::assertEqualsWithDelta(4.9787, $oav, 0.01);
    }

    public function testHalfDecayWhenHalfwayToBoiling(): void
    {
        // bp = 200, T_inert = 50, T = 125 → progress = (125-50)/(200-50) = 0.5
        // k = 0.05, C(20 min) = 100 × exp(-1) ≈ 36.79
        $physical = $this->makePhysical(logP: 0.0, bp: 200);
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 20, temperatureCelsius: 125);

        $oav = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctx);
        self::assertEqualsWithDelta(36.79, $oav, 0.01);
    }

    public function testDecaySaturatesAboveBoilingPoint(): void
    {
        // T = 300 °C, bp = 100 → progress saturé à 1 (pas 2.5)
        $physical = $this->makePhysical(logP: 0.0, bp: 100);
        $ctxAtBp = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 10, temperatureCelsius: 100);
        $ctxAboveBp = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 10, temperatureCelsius: 300);

        self::assertSame(
            $this->calc->effectiveOav($physical, 100.0, 1.0, $ctxAtBp),
            $this->calc->effectiveOav($physical, 100.0, 1.0, $ctxAboveBp),
        );
    }

    // ── Cas réels : eugénol & limonène ────────────────────────────────────────

    public function testRealCaseEugenolInWaterAtRest(): void
    {
        // Eugénol : logP = 2.27 (K_ow ≈ 186), bp = 254 °C, ODT_water = 500 ppm.
        // Bouillon (eau pure), pas de cuisson, 10 000 ppm de concentration.
        // C_water = 10000 / 1 = 10000 (eau pure)
        // OAV_water = 10000 / 500 = 20 → actif mais modéré en bouillon.
        $eugenol = $this->makePhysical(logP: 2.27, bp: 254);
        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.0, waterRatio: 1.0);

        $oav = $this->calc->effectiveOav($eugenol, 10_000.0, 500.0, $ctx);
        self::assertEqualsWithDelta(20.0, $oav, 0.001);
    }

    public function testRealCaseEugenolStaysActiveAfterBoiling(): void
    {
        // Eugénol bp = 254 °C, bouilli 30 min à 100 °C :
        // progress = (100-50)/(254-50) = 0.245 → k ≈ 0.0245
        // retention = exp(-0.0245 × 30) = exp(-0.735) ≈ 0.480
        // C_residual ≈ 10000 × 0.480 = 4800 ppm → OAV ≈ 9.6
        $eugenol = $this->makePhysical(logP: 2.27, bp: 254);
        $ctx = new CulinaryContext(
            OdtMatrix::WATER,
            fatRatio: 0.0,
            waterRatio: 1.0,
            cookingTimeMin: 30,
            temperatureCelsius: 100
        );

        $oav = $this->calc->effectiveOav($eugenol, 10_000.0, 500.0, $ctx);
        self::assertEqualsWithDelta(9.6, $oav, 0.1);
    }

    public function testRealCaseLimoneneFlightsToOilPhase(): void
    {
        // Limonène : logP = 4.57 (K_ow ≈ 37 154), bp = 176 °C.
        // Vinaigrette 25 % huile / 75 % eau, matrix WATER, ODT_water = 50 ppm.
        // denom = 37154 × 0.25 + 0.75 ≈ 9289.25
        // C_water = 5000 / 9289 ≈ 0.538 ppm → OAV_water ≈ 0.011 (imperceptible en phase aqueuse)
        $limonene = $this->makePhysical(logP: 4.57, bp: 176);
        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.25, waterRatio: 0.75);

        $oav = $this->calc->effectiveOav($limonene, 5_000.0, 50.0, $ctx);

        self::assertLessThan(0.05, $oav, 'Limonène est invisible en phase aqueuse — il migre dans l\'huile');
        self::assertEqualsWithDelta(0.01077, $oav, 0.001);
    }

    public function testRealCaseLimoneneIsPerceivedInOilPhaseOfSameMix(): void
    {
        // Même mix 25/75 mais on regarde la phase huile : matrix OIL, ODT_oil = 0.2 ppm
        // C_oil = 5000 × 37154 / 9289.25 ≈ 20 002 ppm → OAV_oil ≈ 100 010
        $limonene = $this->makePhysical(logP: 4.57, bp: 176);
        $ctx = new CulinaryContext(OdtMatrix::OIL, fatRatio: 0.25, waterRatio: 0.75);

        $oav = $this->calc->effectiveOav($limonene, 5_000.0, 0.2, $ctx);

        self::assertGreaterThan(50_000, $oav, 'Limonène est dominant en phase huile');
        self::assertEqualsWithDelta(100_010.0, $oav, 200.0);
    }

    public function testRealCaseLimoneneLosesAromaUnderProlongedHeat(): void
    {
        // Limonène bp = 176 °C, cuisson 30 min à 150 °C en huile pure :
        // progress = (150-50)/(176-50) = 0.794 → k ≈ 0.0794
        // retention = exp(-0.0794 × 30) ≈ exp(-2.38) ≈ 0.0924
        // → 90 %+ perdu après 30 min — vérifier "fenêtre de cuisson" courte.
        $limonene = $this->makePhysical(logP: 4.57, bp: 176);
        $ctx = new CulinaryContext(
            OdtMatrix::OIL,
            fatRatio: 1.0,
            waterRatio: 0.0,
            cookingTimeMin: 30,
            temperatureCelsius: 150
        );

        $oav = $this->calc->effectiveOav($limonene, 1_000.0, 0.2, $ctx);

        // Sans cuisson : C_oil = 1000 × 37154 / 37154 = 1000 → OAV = 5000
        // Avec cuisson : 1000 × 0.0924 = 92.4 → OAV ≈ 462
        self::assertLessThan(700.0, $oav);
        self::assertEqualsWithDelta(462.0, $oav, 10.0);
    }

    // ── needsCorrection() ─────────────────────────────────────────────────────

    public function testNeedsCorrectionFalseForDefaultContext(): void
    {
        self::assertFalse($this->calc->needsCorrection(new CulinaryContext()));
    }

    public function testNeedsCorrectionFalseForPureWaterNoCooking(): void
    {
        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.0, waterRatio: 1.0);
        self::assertFalse($this->calc->needsCorrection($ctx));
    }

    public function testNeedsCorrectionTrueWhenFatRatioPositive(): void
    {
        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.3, waterRatio: 0.7);
        self::assertTrue($this->calc->needsCorrection($ctx));
    }

    public function testNeedsCorrectionTrueWhenCookingTimePositive(): void
    {
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 10);
        self::assertTrue($this->calc->needsCorrection($ctx));
    }

    // ── correctionFactor() ────────────────────────────────────────────────────

    public function testCorrectionFactorIsOneWhenPhysicalIsNull(): void
    {
        self::assertSame(1.0, $this->calc->correctionFactor(null, new CulinaryContext()));
    }

    public function testCorrectionFactorIsOneInNeutralContext(): void
    {
        $physical = $this->makePhysical(logP: 4.0, bp: 200);
        self::assertSame(1.0, $this->calc->correctionFactor($physical, new CulinaryContext()));
    }

    public function testCorrectionFactorAppliesPartitionForMixedPhases(): void
    {
        // K_ow=100, mix 50/50, matrix=WATER → factor = 1/50.5 ≈ 0.0198
        $physical = $this->makePhysical(logP: 2.0);
        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.5, waterRatio: 0.5);

        self::assertEqualsWithDelta(0.01980, $this->calc->correctionFactor($physical, $ctx), 0.001);
    }

    public function testCorrectionFactorIsProductOfPartitionAndDecay(): void
    {
        // mix pure water, no cooking (factor partition=1), cooking 30 min at bp=100 (factor decay=exp(-3))
        $physical = $this->makePhysical(logP: 0.0, bp: 100);
        $ctxCooking = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 30, temperatureCelsius: 100);

        // Pure water mix → partition = 1/(1×0+1) = 1, decay = exp(-0.1×30) ≈ 0.0498
        self::assertEqualsWithDelta(0.04979, $this->calc->correctionFactor($physical, $ctxCooking), 0.001);
    }

    public function testRealCaseBaseNoteSurvivesCookingBetterThanHead(): void
    {
        // Comparaison directe : eugénol (BASE, bp=254) vs limonène (HEAD, bp=176)
        // Même cuisson 20 min à 130 °C, même concentration (1000 ppm), eau pure, matrix WATER.
        $eugenol = $this->makePhysical(logP: 2.27, bp: 254);
        $limonene = $this->makePhysical(logP: 4.57, bp: 176);

        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 20, temperatureCelsius: 130);

        $oavEugenol = $this->calc->effectiveOav($eugenol, 1_000.0, 100.0, $ctx);
        $oavLimonene = $this->calc->effectiveOav($limonene, 1_000.0, 100.0, $ctx);

        self::assertNotNull($oavEugenol);
        self::assertNotNull($oavLimonene);

        // La note de fond doit conserver une fraction beaucoup plus grande que la note de tête
        // (eugénol bp=254 → progress 0.39 ; limonène bp=176 → progress 0.63)
        $retentionEugenol = $oavEugenol / (1_000.0 / 100.0 / (10 ** 2.27 * 0 + 1));
        $retentionLimonene = $oavLimonene / (1_000.0 / 100.0 / (10 ** 4.57 * 0 + 1));

        self::assertGreaterThan($retentionLimonene, $retentionEugenol, 'BASE > HEAD survie à la cuisson');
    }
}
