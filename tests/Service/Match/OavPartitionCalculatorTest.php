<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Entity\AromaticCompound;
use App\Entity\CompoundPhysical;
use App\Enum\OdtMatrix;
use App\Service\Match\OavPartitionCalculator;
use App\ValueObject\Match\CulinaryContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OavPartitionCalculatorTest extends TestCase
{
    private OavPartitionCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new OavPartitionCalculator();
    }

    private function makePhysical(?float $logP = null, ?int $bp = null): CompoundPhysical
    {
        $compound = (new AromaticCompound())->setName('Test');
        $physical = new CompoundPhysical($compound);

        if ($logP !== null) {
            $physical->setLogP($logP);
        }
        if ($bp !== null) {
            $physical->setBoilingPointCelsius($bp);
        }

        return $physical;
    }

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

    public function testFallbackToRawOavWhenPhysicalIsNull(): void
    {
        $oav = $this->calc->effectiveOav(null, 1000.0, 5.0, new CulinaryContext());
        self::assertSame(200.0, $oav);
    }

    public function testFallbackToRawOavWhenLogPMissing(): void
    {
        $physical = $this->makePhysical(logP: null);
        $oav = $this->calc->effectiveOav($physical, 1000.0, 5.0, new CulinaryContext(OdtMatrix::WATER));
        self::assertSame(200.0, $oav);
    }

    public function testPureWaterMixGivesRawOavInWaterMatrix(): void
    {
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
        $oav = $this->calc->effectiveOav(
            $this->makePhysical(logP: 4.0),
            concentrationPpm: 1000.0,
            odtPpm: 0.005,
            ctx: new CulinaryContext(OdtMatrix::AIR),
        );
        self::assertSame(1000.0 / 0.005, $oav);
    }

    public function testHydrophobicCompoundConcentratesInOilPhase(): void
    {
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
        $physical = $this->makePhysical(logP: -1.0);
        $ctxWater = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.5, waterRatio: 0.5);
        $ctxOil = new CulinaryContext(OdtMatrix::OIL, fatRatio: 0.5, waterRatio: 0.5);

        $oavWater = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctxWater);
        $oavOil = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctxOil);

        self::assertGreaterThan($oavOil, $oavWater, 'Composé hydrophile doit se concentrer en eau');
        self::assertEqualsWithDelta(181.818, $oavWater, 0.01);
        self::assertEqualsWithDelta(18.181, $oavOil, 0.01);
    }

    public function testNoDecayWhenCookingTimeIsZero(): void
    {
        $physical = $this->makePhysical(logP: 2.0, bp: 200);
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 0, temperatureCelsius: 100);

        $oav = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctx);

        self::assertSame(100.0, $oav);
    }

    public function testNoDecayBelowInertThreshold(): void
    {
        $physical = $this->makePhysical(logP: 0.0, bp: 100);
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 60, temperatureCelsius: 49);

        $oav = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctx);

        self::assertSame(100.0, $oav);
    }

    public function testNoDecayWhenBoilingPointMissing(): void
    {
        $physical = $this->makePhysical(logP: 0.0, bp: null);
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 60, temperatureCelsius: 100);

        $oav = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctx);
        self::assertSame(100.0, $oav);
    }

    public function testFullDecayAtBoilingPoint(): void
    {
        $physical = $this->makePhysical(logP: 0.0, bp: 100);
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 30, temperatureCelsius: 100);

        $oav = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctx);
        self::assertEqualsWithDelta(4.9787, $oav, 0.01);
    }

    public function testHalfDecayWhenHalfwayToBoiling(): void
    {
        $physical = $this->makePhysical(logP: 0.0, bp: 200);
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 20, temperatureCelsius: 125);

        $oav = $this->calc->effectiveOav($physical, 100.0, 1.0, $ctx);
        self::assertEqualsWithDelta(36.79, $oav, 0.01);
    }

    public function testDecaySaturatesAboveBoilingPoint(): void
    {
        $physical = $this->makePhysical(logP: 0.0, bp: 100);
        $ctxAtBp = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 10, temperatureCelsius: 100);
        $ctxAboveBp = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 10, temperatureCelsius: 300);

        self::assertSame(
            $this->calc->effectiveOav($physical, 100.0, 1.0, $ctxAtBp),
            $this->calc->effectiveOav($physical, 100.0, 1.0, $ctxAboveBp),
        );
    }

    public function testRealCaseEugenolInWaterAtRest(): void
    {
        $eugenol = $this->makePhysical(logP: 2.27, bp: 254);
        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.0, waterRatio: 1.0);

        $oav = $this->calc->effectiveOav($eugenol, 10_000.0, 500.0, $ctx);
        self::assertEqualsWithDelta(20.0, $oav, 0.001);
    }

    public function testRealCaseEugenolStaysActiveAfterBoiling(): void
    {
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
        $limonene = $this->makePhysical(logP: 4.57, bp: 176);
        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.25, waterRatio: 0.75);

        $oav = $this->calc->effectiveOav($limonene, 5_000.0, 50.0, $ctx);

        self::assertLessThan(0.05, $oav, 'Limonène est invisible en phase aqueuse — il migre dans l\'huile');
        self::assertEqualsWithDelta(0.01077, $oav, 0.001);
    }

    public function testRealCaseLimoneneIsPerceivedInOilPhaseOfSameMix(): void
    {
        $limonene = $this->makePhysical(logP: 4.57, bp: 176);
        $ctx = new CulinaryContext(OdtMatrix::OIL, fatRatio: 0.25, waterRatio: 0.75);

        $oav = $this->calc->effectiveOav($limonene, 5_000.0, 0.2, $ctx);

        self::assertGreaterThan(50_000, $oav, 'Limonène est dominant en phase huile');
        self::assertEqualsWithDelta(100_010.0, $oav, 200.0);
    }

    public function testRealCaseLimoneneLosesAromaUnderProlongedHeat(): void
    {
        $limonene = $this->makePhysical(logP: 4.57, bp: 176);
        $ctx = new CulinaryContext(
            OdtMatrix::OIL,
            fatRatio: 1.0,
            waterRatio: 0.0,
            cookingTimeMin: 30,
            temperatureCelsius: 150
        );

        $oav = $this->calc->effectiveOav($limonene, 1_000.0, 0.2, $ctx);

        self::assertLessThan(700.0, $oav);
        self::assertEqualsWithDelta(462.0, $oav, 10.0);
    }

    #[DataProvider('needsCorrectionProvider')]
    public function testNeedsCorrection(bool $expected, CulinaryContext $ctx): void
    {
        self::assertSame($expected, $this->calc->needsCorrection($ctx));
    }

    /**
     * @return iterable<string, array{bool, CulinaryContext}>
     */
    public static function needsCorrectionProvider(): iterable
    {
        yield 'neutral default skips correction' => [false, new CulinaryContext()];
        yield 'pure water without cooking skips correction' => [
            false,
            new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.0, waterRatio: 1.0),
        ];
        yield 'positive fat ratio needs correction' => [
            true,
            new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.3, waterRatio: 0.7),
        ];
        yield 'positive cooking time needs correction' => [
            true,
            new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 10),
        ];
    }

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
        $physical = $this->makePhysical(logP: 2.0);
        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.5, waterRatio: 0.5);

        self::assertEqualsWithDelta(0.01980, $this->calc->correctionFactor($physical, $ctx), 0.001);
    }

    public function testCorrectionFactorIsProductOfPartitionAndDecay(): void
    {
        $physical = $this->makePhysical(logP: 0.0, bp: 100);
        $ctxCooking = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 30, temperatureCelsius: 100);

        self::assertEqualsWithDelta(0.04979, $this->calc->correctionFactor($physical, $ctxCooking), 0.001);
    }

    public function testRealCaseBaseNoteSurvivesCookingBetterThanHead(): void
    {
        $eugenol = $this->makePhysical(logP: 2.27, bp: 254);
        $limonene = $this->makePhysical(logP: 4.57, bp: 176);

        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 20, temperatureCelsius: 130);

        $oavEugenol = $this->calc->effectiveOav($eugenol, 1_000.0, 100.0, $ctx);
        $oavLimonene = $this->calc->effectiveOav($limonene, 1_000.0, 100.0, $ctx);

        self::assertNotNull($oavEugenol);
        self::assertNotNull($oavLimonene);

        $retentionEugenol = $oavEugenol / (1_000.0 / 100.0 / (10 ** 2.27 * 0 + 1));
        $retentionLimonene = $oavLimonene / (1_000.0 / 100.0 / (10 ** 4.57 * 0 + 1));

        self::assertGreaterThan($retentionLimonene, $retentionEugenol, 'BASE > HEAD survie à la cuisson');
    }
}
