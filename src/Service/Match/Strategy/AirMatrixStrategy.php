<?php

declare(strict_types=1);

namespace App\Service\Match\Strategy;

final readonly class AirMatrixStrategy implements MatrixStrategy
{
    public function partitionFactor(float $kOw, float $fatRatio, float $waterRatio): float
    {
        // Pas de phase solvant explicite en air — Henry simplifié, concentration totale supposée perceptible.
        return 1.0;
    }

    public function cacheTtlSeconds(): int
    {
        return 86_400;
    }
}
