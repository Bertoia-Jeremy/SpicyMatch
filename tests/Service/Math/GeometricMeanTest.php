<?php

declare(strict_types=1);

namespace App\Tests\Service\Math;

use App\Service\Math\GeometricMean;
use PHPUnit\Framework\TestCase;

final class GeometricMeanTest extends TestCase
{
    public function testSingleValueReturnsItself(): void
    {
        self::assertEqualsWithDelta(5.0, GeometricMean::of([5.0]), 1e-9);
    }

    public function testTwoEqualValues(): void
    {
        self::assertEqualsWithDelta(3.0, GeometricMean::of([3.0, 3.0]), 1e-9);
    }

    public function testRangeIsSqrtOfProduct(): void
    {
        self::assertEqualsWithDelta(4.0, GeometricMean::ofRange(2.0, 8.0), 1e-9);
    }

    public function testGeomeanLowerThanArithmeticForSpread(): void
    {
        $geo = GeometricMean::ofRange(0.001, 0.1);
        self::assertEqualsWithDelta(0.01, $geo, 1e-9);
        self::assertLessThan((0.001 + 0.1) / 2, $geo);
    }

    public function testHandlesLargeMagnitudeWithoutOverflow(): void
    {
        $geo = GeometricMean::of([1e8, 1e8, 1e8]);
        self::assertEqualsWithDelta(1e8, $geo, 1.0);
    }

    public function testThreeValues(): void
    {
        self::assertEqualsWithDelta(10.0, GeometricMean::of([1.0, 10.0, 100.0]), 1e-9);
    }

    public function testEmptyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GeometricMean::of([]);
    }

    public function testZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GeometricMean::of([1.0, 0.0]);
    }

    public function testNegativeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GeometricMean::ofRange(-1.0, 5.0);
    }
}
