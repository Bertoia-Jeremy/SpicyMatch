<?php

declare(strict_types=1);

namespace App\Tests\Service\Match\Strategy;

use App\Service\Match\Strategy\AirMatrixStrategy;
use App\Service\Match\Strategy\OilMatrixStrategy;
use App\Service\Match\Strategy\WaterMatrixStrategy;
use PHPUnit\Framework\TestCase;

final class MatrixStrategyTest extends TestCase
{
    // ── Air — pas de phase solvant explicite, factor = 1 ─────────────────────

    public function testAirPartitionFactorAlwaysOne(): void
    {
        $strategy = new AirMatrixStrategy();
        self::assertSame(1.0, $strategy->partitionFactor(kOw: 100.0, fatRatio: 0.5, waterRatio: 0.5));
        self::assertSame(1.0, $strategy->partitionFactor(kOw: 10_000.0, fatRatio: 1.0, waterRatio: 0.0));
        self::assertSame(1.0, $strategy->partitionFactor(kOw: 0.1, fatRatio: 0.0, waterRatio: 1.0));
    }

    public function testAirCacheTtl(): void
    {
        self::assertSame(86_400, (new AirMatrixStrategy())->cacheTtlSeconds());
    }

    // ── Water — C_water = 1 / (K_ow × φ_oil + φ_water) ───────────────────────

    public function testWaterPureWaterMixGivesOne(): void
    {
        // φ_oil=0, φ_water=1 → denom=1 → factor=1
        self::assertSame(1.0, (new WaterMatrixStrategy())->partitionFactor(kOw: 100.0, fatRatio: 0.0, waterRatio: 1.0));
    }

    public function testWaterHydrophobic5050EmulsionConcentratesAwayFromWater(): void
    {
        // K_ow=100, 50/50 → denom=50.5 → factor=1/50.5 ≈ 0.0198 (hydrophobe dilué en eau)
        self::assertEqualsWithDelta(
            1.0 / 50.5,
            (new WaterMatrixStrategy())->partitionFactor(kOw: 100.0, fatRatio: 0.5, waterRatio: 0.5),
            1e-9,
        );
    }

    public function testWaterDegenerateRatiosFallbackToOne(): void
    {
        // ratios à 0 → denom 0 → fallback safety 1.0
        self::assertSame(1.0, (new WaterMatrixStrategy())->partitionFactor(kOw: 100.0, fatRatio: 0.0, waterRatio: 0.0));
    }

    public function testWaterCacheTtl(): void
    {
        self::assertSame(3_600, (new WaterMatrixStrategy())->cacheTtlSeconds());
    }

    // ── Oil — C_oil = K_ow / (K_ow × φ_oil + φ_water) ────────────────────────

    public function testOilPureOilMixWithHydrophobeGivesOne(): void
    {
        // K_ow=100, φ_oil=1, φ_water=0 → denom=100 → factor = 100/100 = 1
        self::assertSame(1.0, (new OilMatrixStrategy())->partitionFactor(kOw: 100.0, fatRatio: 1.0, waterRatio: 0.0));
    }

    public function testOilHydrophobicEmulsionConcentratesInOil(): void
    {
        // K_ow=100, 50/50 → denom=50.5 → factor = 100/50.5 ≈ 1.980
        self::assertEqualsWithDelta(
            100.0 / 50.5,
            (new OilMatrixStrategy())->partitionFactor(kOw: 100.0, fatRatio: 0.5, waterRatio: 0.5),
            1e-9,
        );
    }

    public function testOilCacheTtl(): void
    {
        self::assertSame(3_600, (new OilMatrixStrategy())->cacheTtlSeconds());
    }
}
