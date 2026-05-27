<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Entity\AromaticCompound;
use App\Enum\AromaKinetics;
use App\Repository\CompoundPhysicalRepository;
use App\ValueObject\Match\CulinaryContext;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Classe les composés aromatiques d'un mortier selon leur cinétique d'évaporation
 * (HEAD / HEART / BASE) et calcule leur rétention sous le contexte culinaire donné.
 *
 * Cache "match.insights.cache" (TTL 1h) sur (compoundIds_hash, ctx_hash) pour éviter
 * le batch fetch CompoundPhysical + N calculs Nernst+decay sur chaque consultation.
 */
final readonly class CookingTimelineBuilder
{
    public function __construct(
        private CompoundPhysicalRepository $compoundPhysicalRepository,
        private OavPartitionCalculator $partitionCalculator,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param iterable<AromaticCompound> $compounds Composés du mortier (déjà chargés)
     *
     * @return array{
     *   head: list<array{id: int, name: string, retention: ?float, kinetics: ?string}>,
     *   heart: list<array{id: int, name: string, retention: ?float, kinetics: ?string}>,
     *   base: list<array{id: int, name: string, retention: ?float, kinetics: ?string}>,
     *   unknown: list<array{id: int, name: string, retention: null, kinetics: null}>,
     * }
     */
    public function build(iterable $compounds, CulinaryContext $ctx): array
    {
        $indexed = [];
        foreach ($compounds as $compound) {
            $id = $compound->getId();
            if ($id === null) {
                continue;
            }
            $indexed[$id] = $compound;
        }

        if ($indexed === []) {
            return $this->emptyBuckets();
        }

        $cacheKey = $this->cacheKey(array_keys($indexed), $ctx);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $cached = $item->get();
            if (is_array($cached)) {
                /** @var array{head: list<array<string, mixed>>, heart: list<array<string, mixed>>, base: list<array<string, mixed>>, unknown: list<array<string, mixed>>} $cached */
                return $cached;
            }
        }

        $physicals = $this->compoundPhysicalRepository->loadByCompoundIds(array_keys($indexed));

        $buckets = $this->emptyBuckets();

        foreach ($indexed as $id => $compound) {
            $physical = $physicals[$id] ?? null;
            $kinetics = $physical?->aromaKinetics();
            $retention = $physical !== null ? $this->partitionCalculator->correctionFactor($physical, $ctx) : null;

            $entry = [
                'id' => $id,
                'name' => $compound->getName() ?? '?',
                'retention' => $retention,
                'kinetics' => $kinetics?->value,
            ];

            $key = match ($kinetics) {
                AromaKinetics::HEAD => 'head',
                AromaKinetics::HEART => 'heart',
                AromaKinetics::BASE => 'base',
                null => 'unknown',
            };

            $buckets[$key][] = $entry;
        }

        foreach (['head', 'heart', 'base', 'unknown'] as $key) {
            usort(
                $buckets[$key],
                static fn (array $a, array $b) => ($b['retention'] ?? -1.0) <=> ($a['retention'] ?? -1.0),
            );
        }

        $item->set($buckets)
            ->expiresAfter(3600);
        $this->cache->save($item);

        return $buckets;
    }

    /**
     * @return array{head: list<array<string, mixed>>, heart: list<array<string, mixed>>, base: list<array<string, mixed>>, unknown: list<array<string, mixed>>}
     */
    private function emptyBuckets(): array
    {
        return [
            'head' => [],
            'heart' => [],
            'base' => [],
            'unknown' => [],
        ];
    }

    /**
     * @param int[] $compoundIds
     */
    private function cacheKey(array $compoundIds, CulinaryContext $ctx): string
    {
        sort($compoundIds);

        // Signature du contexte déléguée au VO (Refactor #1).
        return sprintf(
            'match.insights.timeline.%s.%s',
            substr(hash('xxh3', implode(',', $compoundIds)), 0, 16),
            $ctx->signatureHash(),
        );
    }
}
