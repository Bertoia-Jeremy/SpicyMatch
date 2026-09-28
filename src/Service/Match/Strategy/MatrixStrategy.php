<?php

declare(strict_types=1);

namespace App\Service\Match\Strategy;

interface MatrixStrategy
{
    /**
     * @param float $kOw        10^logP
     * @param float $fatRatio   ∈ [0, 1]
     * @param float $waterRatio ∈ [0, 1]
     */
    public function partitionFactor(float $kOw, float $fatRatio, float $waterRatio): float;

    public function cacheTtlSeconds(): int;
}
