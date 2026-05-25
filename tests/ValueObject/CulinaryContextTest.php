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
}
