<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\AromaKinetics;
use PHPUnit\Framework\TestCase;

final class AromaKineticsTest extends TestCase
{
    // ── fromBoilingPoint : null safety ─────────────────────────────────────────

    public function testFromBoilingPointReturnsNullWhenInputIsNull(): void
    {
        self::assertNull(AromaKinetics::fromBoilingPoint(null));
    }

    // ── fromBoilingPoint : seuils HEAD / HEART / BASE ─────────────────────────

    public function testFromBoilingPointBelow150IsHead(): void
    {
        self::assertSame(AromaKinetics::HEAD, AromaKinetics::fromBoilingPoint(149));
    }

    public function testFromBoilingPointMinusReturnsHead(): void
    {
        // Cas limite : composé très volatil (ex: acétone bp = 56 °C)
        self::assertSame(AromaKinetics::HEAD, AromaKinetics::fromBoilingPoint(56));
    }

    public function testFromBoilingPointAt150IsHeart(): void
    {
        // Frontière inclusive — 150 °C appartient à HEART, pas à HEAD.
        self::assertSame(AromaKinetics::HEART, AromaKinetics::fromBoilingPoint(150));
    }

    public function testFromBoilingPointAt250IsHeart(): void
    {
        // Frontière inclusive — 250 °C appartient à HEART, pas à BASE.
        self::assertSame(AromaKinetics::HEART, AromaKinetics::fromBoilingPoint(250));
    }

    public function testFromBoilingPointInRangeIsHeart(): void
    {
        // Linalol bp = 198 °C → HEART
        self::assertSame(AromaKinetics::HEART, AromaKinetics::fromBoilingPoint(198));
    }

    public function testFromBoilingPointAbove250IsBase(): void
    {
        // Eugénol bp = 254 °C → BASE
        self::assertSame(AromaKinetics::BASE, AromaKinetics::fromBoilingPoint(254));
    }

    public function testFromBoilingPointHighIsBase(): void
    {
        // Capsaïcine bp ≈ 410 °C → BASE
        self::assertSame(AromaKinetics::BASE, AromaKinetics::fromBoilingPoint(410));
    }

    // ── label() ────────────────────────────────────────────────────────────────

    public function testHeadLabel(): void
    {
        self::assertSame('Tête', AromaKinetics::HEAD->label());
    }

    public function testHeartLabel(): void
    {
        self::assertSame('Cœur', AromaKinetics::HEART->label());
    }

    public function testBaseLabel(): void
    {
        self::assertSame('Fond', AromaKinetics::BASE->label());
    }

    // ── Valeurs enum sérialisables (string-backed) ────────────────────────────

    public function testEnumValuesAreLowercaseStrings(): void
    {
        self::assertSame('head', AromaKinetics::HEAD->value);
        self::assertSame('heart', AromaKinetics::HEART->value);
        self::assertSame('base', AromaKinetics::BASE->value);
    }

    public function testFromValueRoundtrip(): void
    {
        self::assertSame(AromaKinetics::HEART, AromaKinetics::from('heart'));
    }
}
