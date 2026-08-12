<?php

declare(strict_types=1);

namespace App\Tests\ValueObject;

use App\Enum\OdtMatrix;
use App\ValueObject\Match\CulinaryContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CulinaryContextTest extends TestCase
{
    // ── Constructeur & valeurs par défaut ──────────────────────────────────────

    public function testDefaultsAreNeutralAir(): void
    {
        $ctx = new CulinaryContext();

        self::assertSame(OdtMatrix::AIR, $ctx->matrix);
        self::assertSame(0.0, $ctx->fatRatio);
        self::assertSame(1.0, $ctx->waterRatio);
        self::assertSame(0, $ctx->cookingTimeMin);
        self::assertSame(20, $ctx->temperatureCelsius);
    }

    public function testDefaultFactoryReturnsNeutralAir(): void
    {
        $ctx = CulinaryContext::default();

        self::assertSame(OdtMatrix::AIR, $ctx->matrix);
        self::assertFalse($ctx->isCustom());
    }

    public function testIsReadonly(): void
    {
        self::assertTrue((new \ReflectionClass(CulinaryContext::class))->isReadOnly());
    }

    // ── fromRequest : valeurs valides ─────────────────────────────────────────

    #[DataProvider('validMatrixRequestProvider')]
    public function testFromRequestAcceptsValidMatrix(string $raw, OdtMatrix $expected): void
    {
        self::assertSame($expected, CulinaryContext::fromRequest($raw)->matrix);
    }

    /**
     * @return iterable<string, array{string, OdtMatrix}>
     */
    public static function validMatrixRequestProvider(): iterable
    {
        yield 'air' => ['air', OdtMatrix::AIR];
        yield 'water' => ['water', OdtMatrix::WATER];
        yield 'oil' => ['oil', OdtMatrix::OIL];
        yield 'trimmed whitespace' => ['  water  ', OdtMatrix::WATER];
        yield 'case insensitive' => ['AIR', OdtMatrix::AIR];
    }

    // ── fromRequest : valeurs invalides ──────────────────────────────────────

    #[DataProvider('invalidMatrixRequestProvider')]
    public function testFromRequestThrowsOnInvalidMatrix(string $raw): void
    {
        $this->expectException(\ValueError::class);
        CulinaryContext::fromRequest($raw);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMatrixRequestProvider(): iterable
    {
        yield 'unknown matrix' => ['steam'];
        yield 'empty string' => [''];
        yield 'numeric' => ['42'];
    }

    public function testMatrixLabelIsTranslationKey(): void
    {
        $matrix = CulinaryContext::fromRequest('oil')->matrix;

        self::assertSame('oil', $matrix->value);
        self::assertSame('enum.matrix.oil', $matrix->label());
    }

    // ── Ratios / temps / température acceptés ─────────────────────────────────

    public function testSingleArgConstructorLeavesOtherFieldsNeutral(): void
    {
        $ctx = new CulinaryContext(OdtMatrix::WATER);

        self::assertSame(OdtMatrix::WATER, $ctx->matrix);
        self::assertSame(0.0, $ctx->fatRatio);
        self::assertSame(1.0, $ctx->waterRatio);
        self::assertSame(0, $ctx->cookingTimeMin);
        self::assertSame(20, $ctx->temperatureCelsius);
    }

    public function testAcceptsPureOilContext(): void
    {
        $ctx = new CulinaryContext(OdtMatrix::OIL, fatRatio: 1.0, waterRatio: 0.0);
        self::assertSame(1.0, $ctx->fatRatio);
        self::assertSame(0.0, $ctx->waterRatio);
    }

    public function testAcceptsMixedEmulsion(): void
    {
        $ctx = new CulinaryContext(OdtMatrix::OIL, fatRatio: 0.75, waterRatio: 0.25);
        self::assertEqualsWithDelta(1.0, $ctx->fatRatio + $ctx->waterRatio, 0.001);
    }

    public function testAcceptsBoilingTemperature(): void
    {
        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 30, temperatureCelsius: 100);
        self::assertSame(30, $ctx->cookingTimeMin);
        self::assertSame(100, $ctx->temperatureCelsius);
    }

    public function testRatiosWithinToleranceAreAccepted(): void
    {
        $ctx = new CulinaryContext(fatRatio: 0.4, waterRatio: 0.6001);
        self::assertSame(0.4, $ctx->fatRatio);
    }

    public function testCookingTimeZeroIsAccepted(): void
    {
        self::assertSame(0, (new CulinaryContext(cookingTimeMin: 0))->cookingTimeMin);
    }

    // ── Validation : entrées hors plage ──────────────────────────────────────

    #[DataProvider('invalidConstructionProvider')]
    public function testThrowsOnInvalidConstruction(string $expectedMessage, callable $factory): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        $factory();
    }

    /**
     * @return iterable<string, array{string, callable(): CulinaryContext}>
     */
    public static function invalidConstructionProvider(): iterable
    {
        yield 'fat ratio below zero' => [
            'fatRatio',
            static fn () => new CulinaryContext(fatRatio: -0.1, waterRatio: 1.1),
        ];
        yield 'fat ratio above one' => [
            'fatRatio',
            static fn () => new CulinaryContext(fatRatio: 1.5, waterRatio: -0.5),
        ];
        yield 'water ratio below zero' => [
            'waterRatio',
            static fn () => new CulinaryContext(fatRatio: 0.5, waterRatio: -0.5),
        ];
        yield 'ratios do not sum to one' => [
            '≈ 1',
            static fn () => new CulinaryContext(fatRatio: 0.3, waterRatio: 0.3),
        ];
        yield 'negative cooking time' => [
            'cookingTimeMin',
            static fn () => new CulinaryContext(cookingTimeMin: -5),
        ];
    }

    // ── isCustom() ────────────────────────────────────────────────────────────

    #[DataProvider('customContextProvider')]
    public function testIsCustom(bool $expected, CulinaryContext $ctx): void
    {
        self::assertSame($expected, $ctx->isCustom());
    }

    /**
     * @return iterable<string, array{bool, CulinaryContext}>
     */
    public static function customContextProvider(): iterable
    {
        yield 'neutral default is not custom' => [false, new CulinaryContext()];
        yield 'non-air matrix is custom' => [true, new CulinaryContext(OdtMatrix::WATER)];
        yield 'added fat is custom' => [true, new CulinaryContext(fatRatio: 0.2, waterRatio: 0.8)];
        yield 'cooking time is custom' => [true, new CulinaryContext(cookingTimeMin: 10)];
        yield 'temperature change is custom' => [true, new CulinaryContext(temperatureCelsius: 100)];
    }

    // ── getLabel() ────────────────────────────────────────────────────────────

    #[DataProvider('labelProvider')]
    public function testGetLabel(string $expected, CulinaryContext $ctx): void
    {
        self::assertSame($expected, $ctx->getLabel());
    }

    /**
     * @return iterable<string, array{string, CulinaryContext}>
     */
    public static function labelProvider(): iterable
    {
        yield 'default is dry' => ['À sec', new CulinaryContext()];
        yield 'water matrix' => ['Eau', new CulinaryContext(OdtMatrix::WATER)];
        yield 'oil matrix' => ['Huile', new CulinaryContext(OdtMatrix::OIL)];
        yield 'bouillon' => [
            'Bouillon',
            new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 20, temperatureCelsius: 80),
        ];
        yield 'saute' => [
            'Sauté',
            new CulinaryContext(
                OdtMatrix::OIL,
                fatRatio: 1.0,
                waterRatio: 0.0,
                cookingTimeMin: 10,
                temperatureCelsius: 140
            ),
        ];
        yield 'hot emulsion' => [
            'Émulsion chaude',
            new CulinaryContext(
                OdtMatrix::WATER,
                fatRatio: 0.5,
                waterRatio: 0.5,
                cookingTimeMin: 15,
                temperatureCelsius: 70
            ),
        ];
        yield 'confit' => [
            'Confit',
            new CulinaryContext(
                OdtMatrix::OIL,
                fatRatio: 0.0,
                waterRatio: 1.0,
                cookingTimeMin: 60,
                temperatureCelsius: 85
            ),
        ];
    }

    // ── getIcon() ────────────────────────────────────────────────────────────

    #[DataProvider('iconProvider')]
    public function testGetIcon(string $expected, CulinaryContext $ctx): void
    {
        self::assertSame($expected, $ctx->getIcon());
    }

    /**
     * @return iterable<string, array{string, CulinaryContext}>
     */
    public static function iconProvider(): iterable
    {
        yield 'default is wind' => ['fa-wind', new CulinaryContext()];
        yield 'cooking with fat is flame' => [
            'fa-fire-flame-curved',
            new CulinaryContext(
                OdtMatrix::OIL,
                fatRatio: 1.0,
                waterRatio: 0.0,
                cookingTimeMin: 10,
                temperatureCelsius: 140
            ),
        ];
    }
}
