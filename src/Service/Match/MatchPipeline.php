<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Repository\CandidateVetoRepository;
use App\Repository\CompoundPhysicalRepository;
use App\Repository\SpiceActiveCompoundRepository;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;

/**
 * Orchestrateur des étapes du pipeline de compatibilité aromatique.
 *
 * Étapes :
 *  1. Validation (faite en amont par le contrôleur via MortarIds)
 *  2. Construction du profil OAV agrégé du mortier (MortarProfileBuilder, filtré par matrice)
 *  3. Veto SQL biparti → liste des candidats survivants (filtré par matrice)
 *  4. Hydratation OAV des survivants (1 SELECT IN, filtré par matrice)
 *  5. Correction physico-chimique (Étape 3C — partition Nernst + décroissance temporelle)
 *     Appliquée UNIQUEMENT si le contexte introduit une physique non triviale (fat > 0 ou cuisson > 0).
 *     Sinon : skipped → comportement identique aux Étapes 1-2 (rétrocompat des 566 tests).
 *  6. Scoring Tanimoto OAV sur profils corrigés
 *  7. Tri descendant + slicing limit
 *
 * Mode dégradé : si aucune donnée OAV pour la matrice → veto présence + score 0.
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
        private readonly CompoundPhysicalRepository $compoundPhysicalRepository,
        private readonly OavPartitionCalculator $partitionCalculator,
    ) {
    }

    /**
     * Exécute le pipeline complet et retourne les candidats classés par score.
     *
     * @param int             $limit Nombre maximum de résultats (≥ 1, ≤ 100)
     * @param CulinaryContext $ctx   Contexte culinaire — matrice + ratios + cuisson
     *
     * @return list<array{id: int, score: int, oav_mode: bool}>
     */
    public function run(MortarIds $mortar, int $limit = 20, CulinaryContext $ctx = new CulinaryContext()): array
    {
        $matrix = $ctx->matrix;

        $mortarProfile = $this->mortarProfileBuilder->build($mortar, $matrix);
        $oavMode = $mortarProfile !== null;

        $survivorIds = $oavMode
            ? $this->candidateVetoRepository->findSurvivors($mortar, $matrix)
            : $this->candidateVetoRepository->findSurvivorsWithPresence($mortar);

        if ($survivorIds === []) {
            return [];
        }

        if (! $oavMode) {
            return array_slice(
                array_map(static fn (int $id) => [
                    'id' => $id,
                    'score' => 0,
                    'oav_mode' => false,
                ], $survivorIds),
                0,
                $limit,
            );
        }

        $profiles = $this->spiceActiveCompoundRepository->loadOavProfilesBatch($survivorIds, $matrix);

        // Étape 5 — Correction physico-chimique (Étape 3C)
        // Skipped si le contexte est neutre (fatRatio=0 ET cookingTimeMin=0) →
        // factor=1 partout → shadow OAV déjà correcte → économie de requête + CPU.
        if ($this->partitionCalculator->needsCorrection($ctx)) {
            $factors = $this->buildCorrectionFactors(array_keys($mortarProfile), $profiles, $ctx);
            $mortarProfile = $this->applyFactors($mortarProfile, $factors);
            foreach ($profiles as $spiceId => $profile) {
                $profiles[$spiceId] = $this->applyFactors($profile, $factors);
            }
        }

        // Étape 6 — Scoring Tanimoto
        $results = [];
        foreach ($survivorIds as $spiceId) {
            $candidateOav = $profiles[$spiceId] ?? null;
            if ($candidateOav === null) {
                continue;
            }

            $scoreInt = $this->scorer->scoreAsInt($candidateOav, $mortarProfile);
            $results[] = [
                'id' => $spiceId,
                'score' => $scoreInt,
                'oav_mode' => true,
            ];
        }

        usort($results, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /**
     * Construit la map compoundId → facteur correctif en un seul fetch des CompoundPhysical.
     *
     * @param int[]                         $mortarCompoundIds
     * @param array<int, array<int, float>> $candidateProfiles
     *
     * @return array<int, float>
     */
    private function buildCorrectionFactors(
        array $mortarCompoundIds,
        array $candidateProfiles,
        CulinaryContext $ctx,
    ): array {
        $allCompoundIds = $mortarCompoundIds;
        foreach ($candidateProfiles as $profile) {
            foreach (array_keys($profile) as $compoundId) {
                $allCompoundIds[] = $compoundId;
            }
        }
        $uniqueIds = array_values(array_unique($allCompoundIds));

        $physicalMap = $this->compoundPhysicalRepository->loadByCompoundIds($uniqueIds);

        $factors = [];
        foreach ($uniqueIds as $compoundId) {
            $physical = $physicalMap[$compoundId] ?? null;
            $factors[$compoundId] = $this->partitionCalculator->correctionFactor($physical, $ctx);
        }

        return $factors;
    }

    /**
     * @param array<int, float> $profile
     * @param array<int, float> $factors
     *
     * @return array<int, float>
     */
    private function applyFactors(array $profile, array $factors): array
    {
        $corrected = [];
        foreach ($profile as $compoundId => $oav) {
            $factor = $factors[$compoundId] ?? 1.0;
            $corrected[$compoundId] = $oav * $factor;
        }

        return $corrected;
    }
}
