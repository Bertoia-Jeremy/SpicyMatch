<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Spices;
use App\Repository\SpicesRepository;

/**
 * Computes compatibility scores between selected spices and all candidates.
 *
 * Score formula per candidate:
 *   raw = mainCompoundsCount×3 + secondaryCompoundsCount×1 + alchemyFlavorsCount×5 + groupBonus
 *   groupBonus = 10 if candidate's aromatic group matches a selected spice's group
 *   score (0-100) = round(raw / maxRaw × 100)
 *
 * Results are sorted by score descending.
 */
class CompatibilityScoreService
{
    public function __construct(
        private readonly SpicesRepository $spicesRepository,
    ) {
    }

    /**
     * @param Spices[] $selectedSpices
     *
     * @return array<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string, score: int, mainCompoundsCount: int, secondaryCompoundsCount: int, alchemyFlavorsCount: int}>
     */
    public function findCompatible(array $selectedSpices): array
    {
        if (empty($selectedSpices)) {
            return [];
        }

        $n = count($selectedSpices);
        $selectedIds = array_map(fn (Spices $s) => $s->getId(), $selectedSpices);

        // Step 1: Count how many selected spices share each compound (main OR secondary combined)
        $compoundPresence = [];  // compoundId => count of spices that have it
        $compoundEntities = [];  // compoundId => AromaticCompound (for AlchemyFlavors access later)

        foreach ($selectedSpices as $spice) {
            $seen = [];
            foreach ($spice->getAromaticsCompounds() as $compound) {
                $cid = $compound->getId();
                if (!isset($seen[$cid])) {
                    $compoundPresence[$cid] = ($compoundPresence[$cid] ?? 0) + 1;
                    $compoundEntities[$cid] = $compound;
                    $seen[$cid] = true;
                }
            }
            foreach ($spice->getSecondaryAromaticsCompounds() as $compound) {
                $cid = $compound->getId();
                if (!isset($seen[$cid])) {
                    $compoundPresence[$cid] = ($compoundPresence[$cid] ?? 0) + 1;
                    $compoundEntities[$cid] = $compound;
                    $seen[$cid] = true;
                }
            }
        }

        // Step 2: Keep only compounds shared by ALL selected spices
        $sharedCompoundIds = array_keys(array_filter(
            $compoundPresence,
            fn (int $count) => $count >= $n
        ));

        if (empty($sharedCompoundIds)) {
            return [];
        }

        // Step 3: Collect AlchemyFlavor IDs from those shared compounds
        $selectedAlchemyFlavorIds = [];
        foreach ($sharedCompoundIds as $cid) {
            foreach ($compoundEntities[$cid]->getAlchemyFlavors() as $flavor) {
                $selectedAlchemyFlavorIds[$flavor->getId()] = true;
            }
        }

        // Step 4: Load candidate spices (have ≥1 shared compound, not in current selection)
        $candidates = $this->spicesRepository->findCandidatesForScoring(
            $sharedCompoundIds,
            $selectedIds
        );

        if (empty($candidates)) {
            return [];
        }

        // Step 5: Collect selected aromatic group IDs for group bonus
        $selectedGroupIds = array_unique(array_filter(
            array_map(fn (Spices $s) => $s->getAromaticGroups()?->getId(), $selectedSpices)
        ));

        // Step 6: Score each candidate
        $sharedSet = array_flip($sharedCompoundIds);
        $results = [];

        foreach ($candidates as $candidate) {
            $mainCount = 0;
            $secondaryCount = 0;
            $candidateAlchemyFlavorIds = [];

            foreach ($candidate->getAromaticsCompounds() as $compound) {
                if (isset($sharedSet[$compound->getId()])) {
                    ++$mainCount;
                }
                foreach ($compound->getAlchemyFlavors() as $flavor) {
                    $candidateAlchemyFlavorIds[$flavor->getId()] = true;
                }
            }

            foreach ($candidate->getSecondaryAromaticsCompounds() as $compound) {
                if (isset($sharedSet[$compound->getId()])) {
                    ++$secondaryCount;
                }
                foreach ($compound->getAlchemyFlavors() as $flavor) {
                    $candidateAlchemyFlavorIds[$flavor->getId()] = true;
                }
            }

            $alchemyCount = count(array_intersect_key($candidateAlchemyFlavorIds, $selectedAlchemyFlavorIds));
            $groupBonus = in_array($candidate->getAromaticGroups()?->getId(), $selectedGroupIds, true) ? 10 : 0;
            $rawScore = $mainCount * 3 + $secondaryCount + $alchemyCount * 5 + $groupBonus;

            if ($rawScore > 0) {
                $results[] = [
                    'spice'                  => $candidate,
                    'rawScore'               => $rawScore,
                    'mainCompoundsCount'     => $mainCount,
                    'secondaryCompoundsCount' => $secondaryCount,
                    'alchemyFlavorsCount'    => $alchemyCount,
                ];
            }
        }

        if (empty($results)) {
            return [];
        }

        // Step 7: Normalize to 0-100 and build flat output arrays
        $maxRaw = max(array_column($results, 'rawScore'));
        $output = [];

        foreach ($results as $r) {
            /** @var Spices $spice */
            $spice = $r['spice'];
            $output[] = [
                'id'                     => $spice->getId(),
                'name'                   => $spice->getName(),
                'file'                   => $spice->getFile(),
                'color'                  => $spice->getAromaticGroups()?->getColor(),
                'groupName'              => $spice->getAromaticGroups()?->getName(),
                'score'                  => $maxRaw > 0 ? (int) round($r['rawScore'] / $maxRaw * 100) : 0,
                'mainCompoundsCount'     => $r['mainCompoundsCount'],
                'secondaryCompoundsCount' => $r['secondaryCompoundsCount'],
                'alchemyFlavorsCount'    => $r['alchemyFlavorsCount'],
            ];
        }

        usort($output, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $output;
    }
}
