<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\DataConfidence;
use App\Repository\CandidateVetoRepository;
use App\Repository\SpiceActiveCompoundRepository;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;

final readonly class MatchPipeline implements MatchPipelineInterface
{
    public function __construct(
        private MortarProfileBuilder $mortarProfileBuilder,
        private CandidateVetoRepository $candidateVetoRepository,
        private SpiceActiveCompoundRepository $spiceActiveCompoundRepository,
        private OavTanimotoScorer $scorer,
        private OavPartitionCalculator $partitionCalculator,
        private CorrectionApplier $correctionApplier,
        private FlavorGraphHybridizerInterface $hybridizer,
    ) {
    }

    /**
     * @param int $limit ≥ 1, ≤ 100
     * @return list<array{id: int, score: int, oav_mode: bool}>
     */
    public function run(MortarIds $mortar, int $limit, CulinaryContext $ctx, ?DataConfidence $confidence = null): array
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
            $results = array_map(static fn (int $id): array => [
                'id' => $id,
                'score' => 0,
                'oav_mode' => false,
            ], $survivorIds);
        } else {
            $profiles = $this->spiceActiveCompoundRepository->loadOavProfilesBatch($survivorIds, $matrix);

            if ($this->partitionCalculator->needsCorrection($ctx)) {
                $this->correctionApplier->apply($mortarProfile, $profiles, $ctx);
            }

            $results = [];
            foreach ($survivorIds as $spiceId) {
                $candidateOav = $profiles[$spiceId] ?? null;
                if ($candidateOav === null) {
                    continue;
                }

                $results[] = [
                    'id' => $spiceId,
                    'score' => $this->scorer->scoreAsInt($candidateOav, $mortarProfile),
                    'oav_mode' => true,
                ];
            }
        }

        $results = $this->hybridizer->rerank($results, $mortar, $oavMode, $matrix, $confidence);

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }
}
