<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SpicesRepository;

/**
 * Proactive discovery of the best compatible spice groups.
 *
 * Unlike CompatibilityScoreService (which scores candidates given a user selection),
 * this service pre-computes the top pairs and triplets across all spices using
 * SQL self-joins on the pivot tables — much faster than PHP-based iteration.
 *
 * Score formula: sharedMain×3 + sharedSecondary×1 (no group bonus, no alchemy).
 */
class SpiceGroupFinderService
{
    public function __construct(
        private readonly SpicesRepository $spicesRepository,
    ) {
    }

    /**
     * Returns the top compatible spice pairs sorted by shared compound score.
     *
     * @return array<array{score: int, shared_main: int, shared_secondary: int, spices: list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}>}>
     */
    public function findTopPairs(int $limit = 20): array
    {
        $rows = $this->spicesRepository->findTopCompatiblePairs($limit);

        return array_map(fn (array $row) => [
            'score' => (int) $row['score'],
            'shared_main' => (int) $row['shared_main'],
            'shared_secondary' => (int) $row['shared_secondary'],
            'spices' => [
                [
                    'id' => (int) $row['s1_id'],
                    'name' => $row['s1_name'],
                    'file' => $row['s1_file'] ?? null,
                    'color' => $row['s1_color'] ?? null,
                    'groupName' => $row['s1_group'] ?? null,
                ],
                [
                    'id' => (int) $row['s2_id'],
                    'name' => $row['s2_name'],
                    'file' => $row['s2_file'] ?? null,
                    'color' => $row['s2_color'] ?? null,
                    'groupName' => $row['s2_group'] ?? null,
                ],
            ],
        ], $rows);
    }

    /**
     * Returns the top compatible spice triplets with strict intersection (compound in all 3).
     *
     * @return array<array{score: int, shared_main: int, shared_secondary: int, spices: list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}>}>
     */
    public function findTopTriplets(int $limit = 10): array
    {
        $rows = $this->spicesRepository->findTopCompatibleTriplets($limit);

        return array_map(fn (array $row) => [
            'score' => (int) $row['score'],
            'shared_main' => (int) $row['shared_main'],
            'shared_secondary' => (int) $row['shared_secondary'],
            'spices' => [
                [
                    'id' => (int) $row['s1_id'],
                    'name' => $row['s1_name'],
                    'file' => $row['s1_file'] ?? null,
                    'color' => $row['s1_color'] ?? null,
                    'groupName' => $row['s1_group'] ?? null,
                ],
                [
                    'id' => (int) $row['s2_id'],
                    'name' => $row['s2_name'],
                    'file' => $row['s2_file'] ?? null,
                    'color' => $row['s2_color'] ?? null,
                    'groupName' => $row['s2_group'] ?? null,
                ],
                [
                    'id' => (int) $row['s3_id'],
                    'name' => $row['s3_name'],
                    'file' => $row['s3_file'] ?? null,
                    'color' => $row['s3_color'] ?? null,
                    'groupName' => $row['s3_group'] ?? null,
                ],
            ],
        ], $rows);
    }
}
