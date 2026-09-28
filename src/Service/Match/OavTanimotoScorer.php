<?php

declare(strict_types=1);

namespace App\Service\Match;

final class OavTanimotoScorer
{
    /**
     * @param array<int, float> $candidateOav
     * @param array<int, float> $mortarOav
     * @return float ∈ [0, 1]
     */
    public function score(array $candidateOav, array $mortarOav): float
    {
        if ($candidateOav === [] || $mortarOav === []) {
            return 0.0;
        }

        $minSum = 0.0;
        $maxSum = 0.0;

        foreach ($candidateOav as $id => $a) {
            $wa = $this->perceptualWeight($a);
            $wb = $this->perceptualWeight($mortarOav[$id] ?? 0.0);
            $minSum += min($wa, $wb);
            $maxSum += max($wa, $wb);
        }

        foreach ($mortarOav as $id => $b) {
            if (! isset($candidateOav[$id])) {
                $maxSum += $this->perceptualWeight($b);
            }
        }

        return $maxSum > 0.0 ? $minSum / $maxSum : 0.0;
    }

    private function perceptualWeight(float $oav): float
    {
        return $oav > 1.0 ? log($oav) : 0.0;
    }

    /**
     * @param array<int, float> $candidateOav
     * @param array<int, float> $mortarOav
     */
    public function scoreAsInt(array $candidateOav, array $mortarOav): int
    {
        return (int) floor(100 * $this->score($candidateOav, $mortarOav));
    }
}
