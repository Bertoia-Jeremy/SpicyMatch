<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\Repository\FlavorGraphAffinityRepository;
use App\ValueObject\Match\MortarIds;

final class FlavorGraphHybridizer implements FlavorGraphHybridizerInterface
{
    public function __construct(
        private readonly FlavorGraphAffinityRepository $repository,
        private readonly MatchConfidenceAssessorInterface $confidenceAssessor,
    ) {
    }

    public function rerank(
        array $results,
        MortarIds $mortar,
        bool $oavMode,
        OdtMatrix $matrix,
        ?DataConfidence $tier = null,
    ): array {
        if ([] === $results) {
            return $results;
        }

        $weight = $this->oavWeight($oavMode, $tier, $mortar, $matrix);
        $candidateIds = array_map(static fn (array $r): int => $r['id'], $results);
        $profiles = $this->repository->loadPairwiseBatch($candidateIds, $mortar);
        $mortarIds = $mortar->toArray();
        $mortarSize = \count($mortarIds);

        foreach ($results as $i => $result) {
            $profile = $profiles[$result['id']] ?? null;
            if (null === $profile) {
                continue;
            }

            $flavorGraph = $this->meanAffinity($profile, $mortarIds, $mortarSize);
            $oav = $result['score'] / 100.0;
            $results[$i]['score'] = (int) round(100.0 * ($weight * $oav + (1.0 - $weight) * $flavorGraph));
        }

        return $results;
    }

    public function isActive(): bool
    {
        return true;
    }

    private function oavWeight(bool $oavMode, ?DataConfidence $tier, MortarIds $mortar, OdtMatrix $matrix): float
    {
        if (! $oavMode) {
            return 0.0;
        }

        $tier ??= $this->confidenceAssessor->assess($mortar, $matrix);

        return match ($tier) {
            DataConfidence::MEASURED => 0.85,
            DataConfidence::LITERATURE => 0.65,
            DataConfidence::ESTIMATED => 0.40,
            DataConfidence::PLACEHOLDER => 0.15,
        };
    }

    /**
     * @param array<int, float> $profile
     * @param list<int>         $mortarIds
     */
    private function meanAffinity(array $profile, array $mortarIds, int $mortarSize): float
    {
        if (0 === $mortarSize) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($mortarIds as $id) {
            $sum += $profile[$id] ?? 0.0;
        }

        return $sum / $mortarSize;
    }
}
