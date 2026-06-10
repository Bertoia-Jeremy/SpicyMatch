<?php

declare(strict_types=1);

namespace App\Service\Match\Strategy;

final readonly class WaterMatrixStrategy implements MatrixStrategy
{
    public function partitionFactor(float $kOw, float $fatRatio, float $waterRatio): float
    {
        $denom = $kOw * $fatRatio + $waterRatio;

        return $denom > 0.0 ? 1.0 / $denom : 1.0;
    }

    public function cacheTtlSeconds(): int
    {
        return 3_600;
    }
}
