<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Repository\CandidateVetoRepository;
use App\Repository\SpiceActiveCompoundRepository;
use App\ValueObject\Match\MortarIds;

/**
 * Orchestrateur des 6 étapes du pipeline de compatibilité aromatique.
 *
 * Étapes :
 *  1. Validation (faite en amont par le contrôleur via MortarIds)
 *  2. Construction du profil OAV agrégé du mortier (MortarProfileBuilder)
 *  3. Veto SQL biparti → liste des candidats survivants
 *  4. Hydratation OAV des survivants (1 SELECT IN)
 *  5. Scoring Tanimoto OAV × N' candidats
 *  6. Tri descendant + slicing limit
 *
 * Mode dégradé : si aucune donnée OAV n'est disponible (table spice_active_compound vide),
 * le pipeline bascule sur le veto présence-uniquement et retourne les candidats avec score 0.
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §4
 */
final class MatchPipeline
{
    public function __construct(
        private readonly MortarProfileBuilder $mortarProfileBuilder,
        private readonly CandidateVetoRepository $candidateVetoRepository,
        private readonly SpiceActiveCompoundRepository $spiceActiveCompoundRepository,
        private readonly OavTanimotoScorer $scorer,
    ) {
    }

    /**
     * Exécute le pipeline complet et retourne les candidats classés par score.
     *
     * @param int $limit Nombre maximum de résultats (≥ 1, ≤ 100)
     *
     * @return list<array{id: int, score: int, oav_mode: bool}>
     */
    public function run(MortarIds $mortar, int $limit = 20): array
    {
        // Étape 2 — Profil OAV du mortier (cache 24h).
        // build() retourne null si aucune donnée OAV disponible → mode dégradé.
        // Une seule requête au lieu de hasOavDataForSpices() + build() séparés,
        // ce qui élimine la fenêtre TOCTOU entre les deux (RENAME TABLE atomique entre-deux).
        $mortarProfile = $this->mortarProfileBuilder->build($mortar);
        $oavMode = $mortarProfile !== null;

        // Étape 3 — Veto
        $survivorIds = $oavMode
            ? $this->candidateVetoRepository->findSurvivors($mortar)
            : $this->candidateVetoRepository->findSurvivorsWithPresence($mortar);

        if ($survivorIds === []) {
            return [];
        }

        // Mode dégradé : pas de données OAV → score 0 pour tous les survivants
        if (! $oavMode) {
            return array_slice(
                array_map(static fn (int $id) => [
                    'id' => $id,
                    'score' => 0,
                    'oav_mode' => false,
                ], $survivorIds),
                0,
                $limit
            );
        }

        // Étape 4 — Hydratation OAV des survivants (1 SELECT IN)
        $profiles = $this->spiceActiveCompoundRepository->loadOavProfilesBatch($survivorIds);

        // Étape 5 — Scoring Tanimoto OAV
        // Filtre les candidats sans profil : race condition entre veto (étape 3) et hydratation
        // (étape 4) si un rebuild OAV atomique se produit entre les deux.
        // Un score 0 avec oav_mode:true serait sémantiquement trompeur.
        $results = [];
        foreach ($survivorIds as $spiceId) {
            $candidateOav = $profiles[$spiceId] ?? null;
            if ($candidateOav === null) {
                continue; // profil disparu lors d'un rebuild concurrent — skip
            }

            $scoreInt = $this->scorer->scoreAsInt($candidateOav, $mortarProfile);
            $results[] = [
                'id' => $spiceId,
                'score' => $scoreInt,
                'oav_mode' => true,
            ];
        }

        // Étape 6 — Tri descendant + slicing
        usort($results, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }
}
