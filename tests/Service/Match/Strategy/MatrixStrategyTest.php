<?php

declare(strict_types=1);

namespace App\Tests\Service\Match\Strategy;

use App\Service\Match\Strategy\AirMatrixStrategy;
use App\Service\Match\Strategy\OilMatrixStrategy;
use App\Service\Match\Strategy\WaterMatrixStrategy;
use PHPUnit\Framework\TestCase;

final class MatrixStrategyTest extends TestCase
{
    public function testAirPartitionFactorAlwaysOne(): void
    {
        $strategy = new AirMatrixStrategy();
        self::assertSame(1.0, $strategy->partitionFactor(kOw: 100.0, fatRatio: 0.5, waterRatio: 0.5));
        self::assertSame(1.0, $strategy->partitionFactor(kOw: 10_000.0, fatRatio: 1.0, waterRatio: 0.0));
        self::assertSame(1.0, $strategy->partitionFactor(kOw: 0.1, fatRatio: 0.0, waterRatio: 1.0));
    }

    public function testAirCacheTtl(): void
    {
        self::assertSame(86_400, new AirMatrixStrategy()->cacheTtlSeconds());
    }

    public function testWaterPureWaterMixGivesOne(): void
    {
        self::assertSame(1.0, new WaterMatrixStrategy()->partitionFactor(kOw: 100.0, fatRatio: 0.0, waterRatio: 1.0));
    }

    public function testWaterHydrophobic5050EmulsionConcentratesAwayFromWater(): void
    {
        self::assertEqualsWithDelta(
            1.0 / 50.5,
            new WaterMatrixStrategy()
                ->partitionFactor(kOw: 100.0, fatRatio: 0.5, waterRatio: 0.5),
            1e-9,
        );
    }

    public function testWaterDegenerateRatiosFallbackToOne(): void
    {
        self::assertSame(1.0, new WaterMatrixStrategy()->partitionFactor(kOw: 100.0, fatRatio: 0.0, waterRatio: 0.0));
    }

    public function testWaterCacheTtl(): void
    {
        self::assertSame(3_600, new WaterMatrixStrategy()->cacheTtlSeconds());
    }

    public function testOilPureOilMixWithHydrophobeGivesOne(): void
    {
        self::assertSame(1.0, new OilMatrixStrategy()->partitionFactor(kOw: 100.0, fatRatio: 1.0, waterRatio: 0.0));
    }

    public function testOilHydrophobicEmulsionConcentratesInOil(): void
    {
        self::assertEqualsWithDelta(
            100.0 / 50.5,
            new OilMatrixStrategy()
                ->partitionFactor(kOw: 100.0, fatRatio: 0.5, waterRatio: 0.5),
            1e-9,
        );
    }

    public function testOilCacheTtl(): void
    {
        self::assertSame(3_600, new OilMatrixStrategy()->cacheTtlSeconds());
    }
}
