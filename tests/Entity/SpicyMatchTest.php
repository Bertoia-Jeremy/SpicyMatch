<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchResult;
use App\Enum\OdtMatrix;
use App\ValueObject\Match\CulinaryContext;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SpicyMatchTest extends TestCase
{
    private SpicyMatch $match;

    protected function setUp(): void
    {
        $this->match = new SpicyMatch();
    }

    // ── isManual ────────────────────────────────────────────────────────────

    public function testIsManualDefaultsToFalse(): void
    {
        self::assertFalse($this->match->isManual());
    }

    public function testSetIsManualToggle(): void
    {
        $this->match->setIsManual(true);
        self::assertTrue($this->match->isManual());

        $this->match->setIsManual(false);
        self::assertFalse($this->match->isManual());
    }

    // ── Spices collection ───────────────────────────────────────────────────

    public function testAddSpiceIgnoresDuplicate(): void
    {
        $spice = $this->createMock(Spices::class);
        $this->match->addSpice($spice);
        $this->match->addSpice($spice);

        self::assertCount(1, $this->match->getSpices());
    }

    public function testRemoveSpice(): void
    {
        $spice = $this->createMock(Spices::class);
        $this->match->addSpice($spice);
        $this->match->removeSpice($spice);

        self::assertCount(0, $this->match->getSpices());
    }

    public function testGetSpiceCount(): void
    {
        $this->match->addSpice($this->createMock(Spices::class));
        $this->match->addSpice($this->createMock(Spices::class));

        self::assertSame(2, $this->match->getSpiceCount());
    }

    // ── Results collection ──────────────────────────────────────────────────

    public function testAddResultSetsSpicyMatch(): void
    {
        $result = new SpicyMatchResult();
        $this->match->addResult($result);

        self::assertCount(1, $this->match->getResults());
        self::assertSame($this->match, $result->getSpicyMatch());
    }

    public function testAddResultIgnoresDuplicate(): void
    {
        $result = new SpicyMatchResult();
        $this->match->addResult($result);
        $this->match->addResult($result);

        self::assertCount(1, $this->match->getResults());
    }

    public function testManualMatchHasNoResults(): void
    {
        $this->match->setIsManual(true);

        self::assertTrue($this->match->isManual());
        self::assertCount(0, $this->match->getResults());
    }

    // ── Contexte culinaire persisté ──────────────────────────────────────────

    public function testDefaultMatrixIsAir(): void
    {
        self::assertSame(OdtMatrix::AIR, $this->match->getMatrix());
    }

    public function testWaterRatioIsDerivedFromFatRatio(): void
    {
        self::assertSame(1.0, $this->match->getWaterRatio());

        $this->match->setFatRatio(0.3);
        self::assertEqualsWithDelta(0.7, $this->match->getWaterRatio(), 0.001);

        $this->match->setFatRatio(1.0);
        self::assertSame(0.0, $this->match->getWaterRatio());
    }

    public function testWaterRatioIsClampedToValidRange(): void
    {
        $this->match->setFatRatio(1.5);
        self::assertSame(0.0, $this->match->getWaterRatio());

        $this->match->setFatRatio(-0.1);
        self::assertSame(1.0, $this->match->getWaterRatio());
    }

    public function testGetCulinaryContextReturnsHydratedVO(): void
    {
        $this->match->setMatrix(OdtMatrix::OIL);
        $this->match->setFatRatio(0.75);
        $this->match->setCookingTimeMin(15);
        $this->match->setTemperatureCelsius(140);

        $ctx = $this->match->getCulinaryContext();

        self::assertSame(OdtMatrix::OIL, $ctx->matrix);
        self::assertSame(0.75, $ctx->fatRatio);
        self::assertEqualsWithDelta(0.25, $ctx->waterRatio, 0.001);
        self::assertSame(15, $ctx->cookingTimeMin);
        self::assertSame(140, $ctx->temperatureCelsius);
    }

    public function testSetCulinaryContextRoundtrip(): void
    {
        $original = new CulinaryContext(
            OdtMatrix::OIL,
            fatRatio: 0.6,
            waterRatio: 0.4,
            cookingTimeMin: 12,
            temperatureCelsius: 110
        );

        $this->match->setCulinaryContext($original);
        $recovered = $this->match->getCulinaryContext();

        self::assertSame($original->matrix, $recovered->matrix);
        self::assertEqualsWithDelta($original->fatRatio, $recovered->fatRatio, 0.001);
        self::assertEqualsWithDelta($original->waterRatio, $recovered->waterRatio, 0.001);
        self::assertSame($original->cookingTimeMin, $recovered->cookingTimeMin);
        self::assertSame($original->temperatureCelsius, $recovered->temperatureCelsius);
    }
}
