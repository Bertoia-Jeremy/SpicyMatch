<?php

declare(strict_types=1);

namespace App\Tests\ValueObject;

use App\Enum\OdtMatrix;
use App\ValueObject\Match\CulinaryContext;
use PHPUnit\Framework\TestCase;

final class CulinaryContextTest extends TestCase
{
    // ── Constructeur & valeurs par défaut ──────────────────────────────────────

    public function testDefaultMatrixIsAir(): void
    {
        $ctx = new CulinaryContext();
        self::assertSame(OdtMatrix::AIR, $ctx->matrix);
    }

    public function testDefaultFactoryReturnsAir(): void
    {
        $ctx = CulinaryContext::default();
        self::assertSame(OdtMatrix::AIR, $ctx->matrix);
    }

    // ── fromRequest : valeurs valides ─────────────────────────────────────────

    public function testFromRequestAcceptsAir(): void
    {
        $ctx = CulinaryContext::fromRequest('air');
        self::assertSame(OdtMatrix::AIR, $ctx->matrix);
    }

    public function testFromRequestAcceptsWater(): void
    {
        $ctx = CulinaryContext::fromRequest('water');
        self::assertSame(OdtMatrix::WATER, $ctx->matrix);
    }

    public function testFromRequestAcceptsOil(): void
    {
        $ctx = CulinaryContext::fromRequest('oil');
        self::assertSame(OdtMatrix::OIL, $ctx->matrix);
    }

    public function testFromRequestIsTrimmingWhitespace(): void
    {
        $ctx = CulinaryContext::fromRequest('  water  ');
        self::assertSame(OdtMatrix::WATER, $ctx->matrix);
    }

    public function testFromRequestIsCaseInsensitive(): void
    {
        $ctx = CulinaryContext::fromRequest('AIR');
        self::assertSame(OdtMatrix::AIR, $ctx->matrix);
    }

    // ── fromRequest : valeurs invalides ──────────────────────────────────────

    public function testFromRequestThrowsOnInvalidMatrix(): void
    {
        $this->expectException(\ValueError::class);
        CulinaryContext::fromRequest('steam');
    }

    public function testFromRequestThrowsOnEmptyString(): void
    {
        $this->expectException(\ValueError::class);
        CulinaryContext::fromRequest('');
    }

    public function testFromRequestThrowsOnNumeric(): void
    {
        $this->expectException(\ValueError::class);
        CulinaryContext::fromRequest('42');
    }

    // ── Immutabilité (readonly) ───────────────────────────────────────────────

    public function testIsReadonly(): void
    {
        $ctx = CulinaryContext::default();
        $reflection = new \ReflectionClass($ctx);

        self::assertTrue($reflection->isReadOnly(), 'CulinaryContext doit être une classe readonly');
    }

    // ── Valeur de l'enum (intégration OdtMatrix) ─────────────────────────────

    public function testMatrixValueMatchesEnumValue(): void
    {
        $ctx = CulinaryContext::fromRequest('oil');
        self::assertSame('oil', $ctx->matrix->value);
        self::assertSame('Huile', $ctx->matrix->label());
    }

    // ── Phase 3 : extension fatRatio / waterRatio / temps / température ──────

    public function testDefaultRatiosAreWaterOnly(): void
    {
        $ctx = new CulinaryContext();
        self::assertSame(0.0, $ctx->fatRatio);
        self::assertSame(1.0, $ctx->waterRatio);
    }

    public function testDefaultCookingTimeIsZero(): void
    {
        self::assertSame(0, (new CulinaryContext())->cookingTimeMin);
    }

    public function testDefaultTemperatureIs20(): void
    {
        self::assertSame(20, (new CulinaryContext())->temperatureCelsius);
    }

    public function testAcceptsPureOilContext(): void
    {
        $ctx = new CulinaryContext(OdtMatrix::OIL, fatRatio: 1.0, waterRatio: 0.0);
        self::assertSame(1.0, $ctx->fatRatio);
        self::assertSame(0.0, $ctx->waterRatio);
    }

    public function testAcceptsMixedEmulsion(): void
    {
        // Vinaigrette typique : 75 % huile, 25 % eau
        $ctx = new CulinaryContext(OdtMatrix::OIL, fatRatio: 0.75, waterRatio: 0.25);
        self::assertEqualsWithDelta(1.0, $ctx->fatRatio + $ctx->waterRatio, 0.001);
    }

    public function testAcceptsBoilingTemperature(): void
    {
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 30, temperatureCelsius: 100);
        self::assertSame(30, $ctx->cookingTimeMin);
        self::assertSame(100, $ctx->temperatureCelsius);
    }

    // ── Validation : ratios hors plage ────────────────────────────────────────

    public function testThrowsWhenFatRatioBelowZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fatRatio');
        new CulinaryContext(fatRatio: -0.1, waterRatio: 1.1);
    }

    public function testThrowsWhenFatRatioAboveOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('fatRatio');
        new CulinaryContext(fatRatio: 1.5, waterRatio: -0.5);
    }

    public function testThrowsWhenWaterRatioBelowZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('waterRatio');
        new CulinaryContext(fatRatio: 1.5, waterRatio: -0.5);
    }

    // ── Validation : somme des ratios ≠ 1 ────────────────────────────────────

    public function testThrowsWhenRatiosDoNotSumToOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('≈ 1');
        new CulinaryContext(fatRatio: 0.3, waterRatio: 0.3);
    }

    public function testRatiosWithinToleranceAreAccepted(): void
    {
        // Tolérance numérique : 0.0001 d'écart est OK
        $ctx = new CulinaryContext(fatRatio: 0.4, waterRatio: 0.6001);
        self::assertSame(0.4, $ctx->fatRatio);
    }

    // ── Validation : cookingTime négatif ──────────────────────────────────────

    public function testThrowsWhenCookingTimeNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cookingTimeMin');
        new CulinaryContext(cookingTimeMin: -5);
    }

    public function testCookingTimeZeroIsAccepted(): void
    {
        $ctx = new CulinaryContext(cookingTimeMin: 0);
        self::assertSame(0, $ctx->cookingTimeMin);
    }

    // ── Defaults sur arg unique ───────────────────────────────────────────────

    public function testSingleArgConstructorUsesNeutralDefaults(): void
    {
        // matrix seule → fat=0, water=1, time=0, temp=20 (contexte neutre).
        $ctx = new CulinaryContext(OdtMatrix::WATER);

        self::assertSame(OdtMatrix::WATER, $ctx->matrix);
        self::assertSame(0.0, $ctx->fatRatio);
        self::assertSame(1.0, $ctx->waterRatio);
        self::assertSame(0, $ctx->cookingTimeMin);
        self::assertSame(20, $ctx->temperatureCelsius);
    }

    // ── isCustom() ────────────────────────────────────────────────────────────

    public function testIsCustomReturnsFalseForDefault(): void
    {
        self::assertFalse((new CulinaryContext())->isCustom());
    }

    public function testIsCustomReturnsTrueForNonAirMatrix(): void
    {
        self::assertTrue((new CulinaryContext(OdtMatrix::WATER))->isCustom());
    }

    public function testIsCustomReturnsTrueWithFat(): void
    {
        self::assertTrue((new CulinaryContext(fatRatio: 0.2, waterRatio: 0.8))->isCustom());
    }

    public function testIsCustomReturnsTrueWithCooking(): void
    {
        self::assertTrue((new CulinaryContext(cookingTimeMin: 10))->isCustom());
    }

    public function testIsCustomReturnsTrueWithTemperatureChange(): void
    {
        self::assertTrue((new CulinaryContext(temperatureCelsius: 100))->isCustom());
    }

    // ── getLabel() ────────────────────────────────────────────────────────────

    public function testGetLabelForDefault(): void
    {
        self::assertSame('À sec', (new CulinaryContext())->getLabel());
    }

    public function testGetLabelForWaterMatrix(): void
    {
        self::assertSame('Eau', (new CulinaryContext(OdtMatrix::WATER))->getLabel());
    }

    public function testGetLabelForOilMatrix(): void
    {
        self::assertSame('Huile', (new CulinaryContext(OdtMatrix::OIL))->getLabel());
    }

    public function testGetLabelForBouillonCooking(): void
    {
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 20, temperatureCelsius: 80);
        self::assertSame('Bouillon', $ctx->getLabel());
    }

    public function testGetLabelForSaute(): void
    {
        // fat ≥ 0.75 + cooking > 0 → Sauté
        $ctx = new CulinaryContext(
            OdtMatrix::OIL,
            fatRatio: 1.0,
            waterRatio: 0.0,
            cookingTimeMin: 10,
            temperatureCelsius: 140
        );
        self::assertSame('Sauté', $ctx->getLabel());
    }

    public function testGetLabelForEmulsion(): void
    {
        // fat ∈ ]0, 0.75[ + cooking > 0 → Émulsion chaude
        $ctx = new CulinaryContext(
            OdtMatrix::WATER,
            fatRatio: 0.5,
            waterRatio: 0.5,
            cookingTimeMin: 15,
            temperatureCelsius: 70
        );
        self::assertSame('Émulsion chaude', $ctx->getLabel());
    }

    public function testGetLabelForConfit(): void
    {
        $ctx = new CulinaryContext(
            OdtMatrix::OIL,
            fatRatio: 0.0,
            waterRatio: 1.0,
            cookingTimeMin: 60,
            temperatureCelsius: 85
        );
        self::assertSame('Confit', $ctx->getLabel());
    }

    // ── getIcon() ────────────────────────────────────────────────────────────

    public function testGetIconForDefault(): void
    {
        self::assertSame('fa-wind', (new CulinaryContext())->getIcon());
    }

    public function testGetIconForCookingWithFat(): void
    {
        $ctx = new CulinaryContext(
            OdtMatrix::OIL,
            fatRatio: 1.0,
            waterRatio: 0.0,
            cookingTimeMin: 10,
            temperatureCelsius: 140
        );
        self::assertSame('fa-fire-flame-curved', $ctx->getIcon());
    }
}
