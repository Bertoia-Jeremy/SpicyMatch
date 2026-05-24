<?php

declare(strict_types=1);

namespace App\Service\Match;

/**
 * Algorithme 2 : Le Score — Tanimoto pondéré OAV.
 *
 * Calcule la similarité aromatique entre un candidat et le profil agrégé du mortier.
 *
 * Formule :
 *   S_OAV(c, M) = Σ min(OAV_i^c, OAV_i^M) / Σ max(OAV_i^c, OAV_i^M)
 *
 * Implémentation O(N) sans allocation d'union intermédiaire :
 *   1. Itère sur les composés du candidat → contribue min/max(c, m)
 *   2. Itère sur les composés du mortier absents du candidat → contribue max(0, m) = m
 *
 * Propriétés : borné [0,1], symétrique, monotone.
 * Score affiché : α = floor(100 × S_OAV).
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §3
 */
final class OavTanimotoScorer
{
    /**
     * Calcule le score de Tanimoto pondéré OAV entre un candidat et le mortier.
     *
     * @param array<int, float> $candidateOav compound_id => OAV du candidat
     * @param array<int, float> $mortarOav    compound_id => OAV agrégé du mortier (max par molécule)
     *
     * @return float Score dans [0.0, 1.0]. 0.0 si les ensembles sont disjoints ou vides.
     */
    public function score(array $candidateOav, array $mortarOav): float
    {
        if ($candidateOav === [] || $mortarOav === []) {
            return 0.0;
        }

        $minSum = 0.0;
        $maxSum = 0.0;

        // Parcours des composés du candidat — couvre l'intersection ET les exclusifs candidat
        foreach ($candidateOav as $id => $a) {
            $b = $mortarOav[$id] ?? 0.0;
            $minSum += min($a, $b);
            $maxSum += max($a, $b);
        }

        // Parcours des composés du mortier absents du candidat — min=0, max=b
        foreach ($mortarOav as $id => $b) {
            if (! isset($candidateOav[$id])) {
                $maxSum += $b; // min(0, b)=0 ne contribue pas à minSum
            }
        }

        return $maxSum > 0.0 ? $minSum / $maxSum : 0.0;
    }

    /**
     * Score affiché en entier sur 100 (floor).
     *
     * @param array<int, float> $candidateOav
     * @param array<int, float> $mortarOav
     */
    public function scoreAsInt(array $candidateOav, array $mortarOav): int
    {
        return (int) floor(100 * $this->score($candidateOav, $mortarOav));
    }
}
