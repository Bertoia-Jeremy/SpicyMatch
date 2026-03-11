<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Spices;
use App\Repository\SpicesRepository;

/**
 * Computes compatibility scores between selected spices and all candidates.
 *
 * Score formula per candidate (Jaccard-like, absolute 0-100):
 *   candidateMax = candidateMainCount×3 + candidateSecondaryCount×1
 *   raw          = sharedMainCount×3 + sharedSecondaryCount×1
 *   score (0-100) = min(100, round(raw / candidateMax × 100))
 *
 * Only aromatic compounds (main + secondary) are taken into account.
 * AlchemyFlavors and aromatic groups do NOT impact the score.
 * Intersection is strict: a compound counts only if present in ALL selected spices.
 * No limit on the number of selected spices.
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

        foreach ($selectedSpices as $spice) {
            $seen = [];
            foreach ($spice->getAromaticsCompounds() as $compound) {
                $cid = $compound->getId();
                if (! isset($seen[$cid])) {
                    $compoundPresence[$cid] = ($compoundPresence[$cid] ?? 0) + 1;
                    $seen[$cid] = true;
                }
            }
            foreach ($spice->getSecondaryAromaticsCompounds() as $compound) {
                $cid = $compound->getId();
                if (! isset($seen[$cid])) {
                    $compoundPresence[$cid] = ($compoundPresence[$cid] ?? 0) + 1;
                    $seen[$cid] = true;
                }
            }
        }

        // Step 2: Keep only compounds shared by ALL selected spices (strict intersection)
        $sharedCompoundIds = array_keys(array_filter($compoundPresence, fn (int $count) => $count >= $n));

        if (empty($sharedCompoundIds)) {
            return [];
        }

        // Step 3: Load candidate spices (have ≥1 shared compound, not in current selection)
        $candidates = $this->spicesRepository->findCandidatesForScoring($sharedCompoundIds, $selectedIds);

        if (empty($candidates)) {
            return [];
        }

        // Step 4: Score each candidate using Jaccard-like formula
        $sharedSet = array_flip($sharedCompoundIds);
        $results = [];

        foreach ($candidates as $candidate) {
            $mainCount = 0;
            $secondaryCount = 0;
            $candidateMainTotal = 0;
            $candidateSecTotal = 0;

            foreach ($candidate->getAromaticsCompounds() as $compound) {
                ++$candidateMainTotal;
                if (isset($sharedSet[$compound->getId()])) {
                    ++$mainCount;
                }
            }

            foreach ($candidate->getSecondaryAromaticsCompounds() as $compound) {
                ++$candidateSecTotal;
                if (isset($sharedSet[$compound->getId()])) {
                    ++$secondaryCount;
                }
            }

            $candidateMax = $candidateMainTotal * 3 + $candidateSecTotal;
            if ($candidateMax === 0) {
                continue;
            }

            $raw = $mainCount * 3 + $secondaryCount;
            if ($raw === 0) {
                continue;
            }

            $score = min(100, (int) round($raw / $candidateMax * 100));

            $results[] = [
                'spice' => $candidate,
                'score' => $score,
                'mainCompoundsCount' => $mainCount,
                'secondaryCompoundsCount' => $secondaryCount,
            ];
        }

        if (empty($results)) {
            return [];
        }

        // Step 5: Build flat output arrays sorted by score descending
        usort($results, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $output = [];
        foreach ($results as $r) {
            /** @var Spices $spice */
            $spice = $r['spice'];
            $output[] = [
                'id' => $spice->getId(),
                'name' => $spice->getName(),
                'file' => $spice->getFile(),
                'agId' => $spice->getAromaticGroups()?->getId(),
                'color' => $spice->getAromaticGroups()?->getColor(),
                'groupName' => $spice->getAromaticGroups()?->getName(),
                'stId' => $spice->getSpicyType()?->getId(),
                'score' => $r['score'],
                'mainCompoundsCount' => $r['mainCompoundsCount'],
                'secondaryCompoundsCount' => $r['secondaryCompoundsCount'],
                'alchemyFlavorsCount' => 0,  // kept for backwards compat, not used in scoring
            ];
        }

        return $output;
    }
}
