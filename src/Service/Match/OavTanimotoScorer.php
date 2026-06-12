<?php

declare(strict_types=1);

namespace App\Service\Match;

/**
 * Tanimoto pondéré OAV log-compressé : w_i = OAV > 1 ? ln(OAV) : 0 ; S = Σmin(w_c,w_M)/Σmax.
 *
 * Pourquoi le log : OAV s'étale sur ~6 ordres de grandeur, en linéaire le composé
 * dominant écrase tout et la majorité des candidats compatibles scorent 0%.
 * La perception olfactive étant log (Weber-Fechner), pondérer par ln(OAV) reflète
 * l'intensité perçue.
 *
 * Clamp à 0 sous OAV=1 : sous le seuil van Gemert. Gère aussi les OAV<1 post-Nernst.
 * Base log neutre (simplifie dans le ratio). Affiché ×100 (floor).
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §3
 */
final class OavTanimotoScorer
{
    /**
     * @param array<int, float> $candidateOav
     * @param array<int, float> $mortarOav
     *
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
