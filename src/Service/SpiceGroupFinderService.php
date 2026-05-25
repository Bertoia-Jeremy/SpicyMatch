<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SpicesRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Proactive discovery of the best compatible spice groups.
 *
 * Unlike CompatibilityScoreService (which scores candidates given a user selection),
 * this service pre-computes the top pairs and triplets across all spices using
 * SQL self-joins on the pivot tables — much faster than PHP-based iteration.
 *
 * Score formula: sharedMain×3 + sharedSecondary×1 (no group bonus, no alchemy).
 *
 * Results are cached in `spice.compatibility.cache` (TTL 1h). The underlying SQL
 * is O(N²) for pairs and O(N³) for triplets — unsuitable for per-request execution
 * at scale. Cache invalidation is TTL-based (no event-driven invalidation — MVP).
 */
class SpiceGroupFinderService
{
    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Returns the top compatible spice pairs sorted by shared compound score.
     * Result is cached for 1h (TTL-based).
     *
     * @return array<array{score: int, shared_main: int, shared_secondary: int, spices: list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}>}>
     */
    public function findTopPairs(int $limit = 20): array
    {
        return $this->cache->get('spice.top_pairs.' . $limit, function (ItemInterface $item) use ($limit): array {
            $item->expiresAfter(3600);

            /** @var list<array<string, mixed>> $rows */
            $rows = $this->spicesRepository->findTopCompatiblePairs($limit);

            return $this->formatPairs($rows);
        });
    }

    /**
     * Returns the top compatible spice triplets with strict intersection (compound in all 3).
     * Result is cached for 1h (TTL-based, O(N³) SQL query).
     *
     * @return array<array{score: int, shared_main: int, shared_secondary: int, spices: list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}>}>
     */
    public function findTopTriplets(int $limit = 10): array
    {
        return $this->cache->get('spice.top_triplets.' . $limit, function (ItemInterface $item) use ($limit): array {
            $item->expiresAfter(3600);

            /** @var list<array<string, mixed>> $rows */
            $rows = $this->spicesRepository->findTopCompatibleTriplets($limit);

            return $this->formatTriplets($rows);
        });
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{score: int, shared_main: int, shared_secondary: int, spices: list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}>}>
     */
    private function formatPairs(array $rows): array
    {
        return array_map(fn (array $row) => [
            'score' => (int) $row['score'],
            'shared_main' => (int) ($row['shared_main'] ?? 0),
            'shared_secondary' => (int) ($row['shared_secondary'] ?? 0),
            'spices' => [
                [
                    'id' => (int) $row['s1_id'],
                    'name' => (string) $row['s1_name'],
                    'file' => isset($row['s1_file']) ? (string) $row['s1_file'] : null,
                    'color' => isset($row['s1_color']) ? (string) $row['s1_color'] : null,
                    'groupName' => isset($row['s1_group']) ? (string) $row['s1_group'] : null,
                ],
                [
                    'id' => (int) $row['s2_id'],
                    'name' => (string) $row['s2_name'],
                    'file' => isset($row['s2_file']) ? (string) $row['s2_file'] : null,
                    'color' => isset($row['s2_color']) ? (string) $row['s2_color'] : null,
                    'groupName' => isset($row['s2_group']) ? (string) $row['s2_group'] : null,
                ],
            ],
        ], $rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{score: int, shared_main: int, shared_secondary: int, spices: list<array{id: int, name: string, file: ?string, color: ?string, groupName: ?string}>}>
     */
    private function formatTriplets(array $rows): array
    {
        return array_map(fn (array $row) => [
            'score' => (int) $row['score'],
            'shared_main' => (int) ($row['shared_main'] ?? 0),
            'shared_secondary' => (int) ($row['shared_secondary'] ?? 0),
            'spices' => [
                [
                    'id' => (int) $row['s1_id'],
                    'name' => (string) $row['s1_name'],
                    'file' => isset($row['s1_file']) ? (string) $row['s1_file'] : null,
                    'color' => isset($row['s1_color']) ? (string) $row['s1_color'] : null,
                    'groupName' => isset($row['s1_group']) ? (string) $row['s1_group'] : null,
                ],
                [
                    'id' => (int) $row['s2_id'],
                    'name' => (string) $row['s2_name'],
                    'file' => isset($row['s2_file']) ? (string) $row['s2_file'] : null,
                    'color' => isset($row['s2_color']) ? (string) $row['s2_color'] : null,
                    'groupName' => isset($row['s2_group']) ? (string) $row['s2_group'] : null,
                ],
                [
                    'id' => (int) $row['s3_id'],
                    'name' => (string) $row['s3_name'],
                    'file' => isset($row['s3_file']) ? (string) $row['s3_file'] : null,
                    'color' => isset($row['s3_color']) ? (string) $row['s3_color'] : null,
                    'groupName' => isset($row['s3_group']) ? (string) $row['s3_group'] : null,
                ],
            ],
        ], $rows);
    }
}
