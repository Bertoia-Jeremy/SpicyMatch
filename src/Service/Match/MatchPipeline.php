<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Repository\CandidateVetoRepository;
use App\Repository\SpiceActiveCompoundRepository;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;

/**
 * Pipeline OAV : profil mortier → veto → hydratation → correction (si ctx non neutre) → Tanimoto.
 * Mode dégradé : aucune donnée OAV pour la matrice → veto présence + score 0.
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §4
 */
final class MatchPipeline implements MatchPipelineInterface
{
    public function __construct(
        private readonly MortarProfileBuilder $mortarProfileBuilder,
        private readonly CandidateVetoRepository $candidateVetoRepository,
        private readonly SpiceActiveCompoundRepository $spiceActiveCompoundRepository,
        private readonly OavTanimotoScorer $scorer,
        private readonly OavPartitionCalculator $partitionCalculator,
        private readonly CorrectionApplier $correctionApplier,
    ) {
    }

    /**
     * @param int $limit ≥ 1, ≤ 100
     *
     * @return list<array{id: int, score: int, oav_mode: bool}>
     */
    public function run(MortarIds $mortar, int $limit, CulinaryContext $ctx): array
    {
        $matrix = $ctx->matrix;

        $mortarProfile = $this->mortarProfileBuilder->build($mortar, $matrix);
        $oavMode = null !== $mortarProfile;

        $survivorIds = $oavMode
            ? $this->candidateVetoRepository->findSurvivors($mortar, $matrix)
            : $this->candidateVetoRepository->findSurvivorsWithPresence($mortar);

        if ([] === $survivorIds) {
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

        if ($this->partitionCalculator->needsCorrection($ctx)) {
            $this->correctionApplier->apply($mortarProfile, $profiles, $ctx);
        }

        $results = [];
        foreach ($survivorIds as $spiceId) {
            $candidateOav = $profiles[$spiceId] ?? null;
            if (null === $candidateOav) {
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
}
