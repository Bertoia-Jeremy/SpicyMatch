<?php

declare(strict_types=1);

namespace App\Service\Match;

/**
 * Algorithme 2 : Le Score — Tanimoto pondéré OAV (log-compressé).
 *
 * Calcule la similarité aromatique entre un candidat et le profil agrégé du mortier.
 *
 * Formule :
 *   w_i = OAV_i > 1 ? ln(OAV_i) : 0          (compression perceptuelle)
 *   S_OAV(c, M) = Σ min(w_i^c, w_i^M) / Σ max(w_i^c, w_i^M)
 *
 * Pourquoi le log ?
 *   L'OAV brut (concentration/seuil) s'étale sur ~6 ordres de grandeur (1 → 10^8).
 *   En Tanimoto linéaire, le composé le plus actif écrase numériquement tous les
 *   autres → la majorité des candidats compatibles (passant le veto) scorent 0 %.
 *   La perception olfactive étant logarithmique (Weber-Fechner / Stevens), pondérer
 *   par ln(OAV) reflète l'intensité PERÇUE et redonne du poids à chaque note.
 *
 *   Clamp à 0 sous OAV = 1 : un composé sous son seuil de détection (van Gemert)
 *   est imperceptible → poids nul. Gère aussi les OAV < 1 issus de la correction
 *   Nernst (Étape 3C, multiplication runtime) sans produire de log négatif.
 *
 *   Le choix de la base est neutre : le facteur 1/ln(b) se simplifie dans le ratio.
 *
 * Implémentation O(N) sans allocation d'union intermédiaire :
 *   1. Itère sur les composés du candidat → contribue min/max(w_c, w_m)
 *   2. Itère sur les composés du mortier absents du candidat → contribue max(0, w_m) = w_m
 *
 * Propriétés : borné [0,1], symétrique, monotone.
 * Score affiché : α = floor(100 × S_OAV).
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §3
 */
final class OavTanimotoScorer
{
    /**
     * Calcule le score de Tanimoto pondéré OAV (log-compressé) entre un candidat et le mortier.
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
            $wa = $this->perceptualWeight($a);
            $wb = $this->perceptualWeight($mortarOav[$id] ?? 0.0);
            $minSum += min($wa, $wb);
            $maxSum += max($wa, $wb);
        }

        // Parcours des composés du mortier absents du candidat — min=0, max=w_m
        foreach ($mortarOav as $id => $b) {
            if (! isset($candidateOav[$id])) {
                $maxSum += $this->perceptualWeight($b); // min(0, w_m)=0 ne contribue pas à minSum
            }
        }

        return $maxSum > 0.0 ? $minSum / $maxSum : 0.0;
    }

    /**
     * Poids perceptuel d'un OAV : ln(OAV) compressé, clampé à 0 sous le seuil de détection.
     *
     * OAV ≤ 1 → composé imperceptible (van Gemert) ou supprimé par correction Nernst → 0.
     */
    private function perceptualWeight(float $oav): float
    {
        return $oav > 1.0 ? log($oav) : 0.0;
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
