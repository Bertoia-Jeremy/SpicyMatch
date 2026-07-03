<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Repository\CompoundPhysicalRepositoryInterface;
use App\ValueObject\Match\CulinaryContext;

/**
 * Applique la correction physico-chimique aux profils OAV.
 * L'appelant garantit que la correction est requise (cf. OavPartitionCalculator::needsCorrection()).
 */
final readonly class CorrectionApplier
{
    public function __construct(
        private CompoundPhysicalRepositoryInterface $compoundPhysicalRepository,
        private OavPartitionCalculator $partitionCalculator,
    ) {
    }

    /**
     * @param array<int, float>             $mortarProfile     ref — compound_id => OAV
     * @param array<int, array<int, float>> $candidateProfiles ref — spice_id => [compound_id => OAV]
     */
    public function apply(array &$mortarProfile, array &$candidateProfiles, CulinaryContext $ctx): void
    {
        $factors = $this->buildCorrectionFactors(array_keys($mortarProfile), $candidateProfiles, $ctx);

        $this->applyFactorsInPlace($mortarProfile, $factors);
        foreach ($candidateProfiles as &$profile) {
            $this->applyFactorsInPlace($profile, $factors);
        }
        unset($profile);
    }

    /**
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
     * @param array<int, float> $profile ref
     * @param array<int, float> $factors
     */
    private function applyFactorsInPlace(array &$profile, array $factors): void
    {
        foreach ($profile as $compoundId => $oav) {
            $factor = $factors[$compoundId] ?? 1.0;
            if (1.0 !== $factor) {
                $profile[$compoundId] = $oav * $factor;
            }
        }
    }
}
