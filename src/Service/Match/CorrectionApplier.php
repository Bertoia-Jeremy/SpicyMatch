<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Repository\CompoundPhysicalRepository;
use App\ValueObject\Match\CulinaryContext;

/**
 * Applique la correction physico-chimique (Nernst + décroissance) aux profils OAV
 * du mortier et des candidats (Refacto SRP — extraction depuis MatchPipeline).
 *
 * Responsabilité unique : étant donné un contexte non neutre, calculer les facteurs
 * correctifs par composé et les appliquer in-place aux profils. Le pipeline délègue
 * et ne s'occupe plus du détail (orchestration vs calcul = couches distinctes).
 *
 * Skip-fast : le pipeline appelle `needsCorrection()` du calculator AVANT d'invoquer
 * cet applier — donc ici on suppose que la correction est requise (pas de re-check).
 */
final readonly class CorrectionApplier
{
    public function __construct(
        private CompoundPhysicalRepository $compoundPhysicalRepository,
        private OavPartitionCalculator $partitionCalculator,
    ) {
    }

    /**
     * Applique la correction in-place sur le profil mortier ET sur tous les profils candidats.
     * Un seul batch fetch CompoundPhysical, factor map réutilisée pour tous les profils.
     *
     * @param array<int, float>             $mortarProfile     ref — compound_id => OAV
     * @param array<int, array<int, float>> $candidateProfiles ref — spice_id => [compound_id => OAV]
     */
    public function apply(array &$mortarProfile, array &$candidateProfiles, CulinaryContext $ctx): void
    {
        $factors = $this->buildCorrectionFactors(array_keys($mortarProfile), $candidateProfiles, $ctx);

        $this->applyFactorsInPlace($mortarProfile, $factors);
        foreach ($candidateProfiles as $spiceId => &$profile) {
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
     * Application in-place : factor=1.0 → no-op (évite la mutation inutile).
     *
     * @param array<int, float> $profile ref
     * @param array<int, float> $factors
     */
    private function applyFactorsInPlace(array &$profile, array $factors): void
    {
        foreach ($profile as $compoundId => $oav) {
            $factor = $factors[$compoundId] ?? 1.0;
            if ($factor !== 1.0) {
                $profile[$compoundId] = $oav * $factor;
            }
        }
    }
}
