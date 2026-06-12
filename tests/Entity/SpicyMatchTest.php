<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchResult;
use App\Entity\Users;
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

    public function testSetIsManualToTrue(): void
    {
        $result = $this->match->setIsManual(true);

        self::assertTrue($this->match->isManual());
        self::assertSame($this->match, $result, 'setIsManual() should be fluent');
    }

    public function testSetIsManualToggle(): void
    {
        $this->match->setIsManual(true);
        self::assertTrue($this->match->isManual());

        $this->match->setIsManual(false);
        self::assertFalse($this->match->isManual());
    }

    // ── Constructor defaults ────────────────────────────────────────────────

    public function testConstructorInitializesEmptyCollections(): void
    {
        self::assertCount(0, $this->match->getSpices());
        self::assertCount(0, $this->match->getResults());
        self::assertCount(0, $this->match->getSpicyMatchHistories());
    }

    public function testConstructorInitializesTimestamps(): void
    {
        self::assertInstanceOf(\DateTimeImmutable::class, $this->match->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $this->match->getUpdatedAt());
        self::assertNull($this->match->getDeletedAt());
    }

    // ── User ────────────────────────────────────────────────────────────────

    public function testUserIsNullByDefault(): void
    {
        self::assertNull($this->match->getUser());
    }

    public function testSetUser(): void
    {
        $user = $this->createMock(Users::class);
        $this->match->setUser($user);

        self::assertSame($user, $this->match->getUser());
    }

    // ── Spices collection ───────────────────────────────────────────────────

    public function testAddSpice(): void
    {
        $spice = $this->createMock(Spices::class);
        $result = $this->match->addSpice($spice);

        self::assertCount(1, $this->match->getSpices());
        self::assertSame($this->match, $result);
    }

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
        $s1 = $this->createMock(Spices::class);
        $s2 = $this->createMock(Spices::class);
        $this->match->addSpice($s1);
        $this->match->addSpice($s2);

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

    // ── Timestamps ──────────────────────────────────────────────────────────

    public function testSetDeletedAt(): void
    {
        $now = new \DateTimeImmutable();
        $this->match->setDeletedAt($now);

        self::assertSame($now, $this->match->getDeletedAt());
    }

    public function testSetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2025-01-01');
        $this->match->setCreatedAt($date);

        self::assertSame($date, $this->match->getCreatedAt());
    }

    public function testSetUpdatedAt(): void
    {
        $date = new \DateTimeImmutable('2025-06-15');
        $this->match->setUpdatedAt($date);

        self::assertSame($date, $this->match->getUpdatedAt());
    }

    // ── Contexte culinaire persisté ──────────────────────────────────────────

    public function testDefaultMatrixIsAir(): void
    {
        self::assertSame(OdtMatrix::AIR, $this->match->getMatrix());
    }

    public function testDefaultFatRatioIsZero(): void
    {
        self::assertSame(0.0, $this->match->getFatRatio());
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
        // En cas de fat hors plage stocké (ex: vieille donnée), waterRatio reste ∈ [0, 1]
        $this->match->setFatRatio(1.5);
        self::assertSame(0.0, $this->match->getWaterRatio());

        $this->match->setFatRatio(-0.1);
        self::assertSame(1.0, $this->match->getWaterRatio());
    }

    public function testDefaultCookingTimeIsZero(): void
    {
        self::assertSame(0, $this->match->getCookingTimeMin());
    }

    public function testDefaultTemperatureIs20(): void
    {
        self::assertSame(20, $this->match->getTemperatureCelsius());
    }

    public function testSetMatrixIsFluent(): void
    {
        $result = $this->match->setMatrix(OdtMatrix::WATER);
        self::assertSame($this->match, $result);
        self::assertSame(OdtMatrix::WATER, $this->match->getMatrix());
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

    public function testSetCulinaryContextWritesAllFields(): void
    {
        $ctx = new CulinaryContext(
            OdtMatrix::WATER,
            fatRatio: 0.25,
            waterRatio: 0.75,
            cookingTimeMin: 30,
            temperatureCelsius: 95
        );

        $this->match->setCulinaryContext($ctx);

        self::assertSame(OdtMatrix::WATER, $this->match->getMatrix());
        self::assertSame(0.25, $this->match->getFatRatio());
        self::assertSame(30, $this->match->getCookingTimeMin());
        self::assertSame(95, $this->match->getTemperatureCelsius());
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
