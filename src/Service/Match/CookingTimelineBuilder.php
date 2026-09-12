<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Entity\AromaticCompound;
use App\Enum\AromaKinetics;
use App\Repository\CompoundPhysicalRepositoryInterface;
use App\ValueObject\Match\CulinaryContext;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Classe les composés d'un mortier par cinétique (HEAD/HEART/BASE) + rétention sous ctx.
 * Cache TTL 1h sur (compoundIds, ctx).
 */
final readonly class CookingTimelineBuilder
{
    public function __construct(
        private CompoundPhysicalRepositoryInterface $compoundPhysicalRepository,
        private OavPartitionCalculator $partitionCalculator,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param iterable<AromaticCompound> $compounds
     *
     * @return array{head: list<TimelineEntry>, heart: list<TimelineEntry>, base: list<TimelineEntry>, unknown: list<TimelineEntry>}
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
                /** @var array{head: list<TimelineEntry>, heart: list<TimelineEntry>, base: list<TimelineEntry>, unknown: list<TimelineEntry>} $cached */
                return $cached;
            }
        }

        $physicals = $this->compoundPhysicalRepository->loadByCompoundIds(array_keys($indexed));

        $buckets = $this->emptyBuckets();

        foreach ($indexed as $id => $compound) {
            $physical = $physicals[$id] ?? null;
            $kinetics = $physical?->aromaKinetics();
            $retention = $physical !== null ? $this->partitionCalculator->correctionFactor($physical, $ctx) : null;

            $entry = new TimelineEntry(
                id: $id,
                name: $compound->getName() ?? '?',
                retention: $retention,
                kinetics: $kinetics?->value,
            );

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
                static fn (TimelineEntry $a, TimelineEntry $b) => ($b->retention ?? -1.0) <=> ($a->retention ?? -1.0),
            );
        }

        $item->set($buckets)
            ->expiresAfter(3600);
        $this->cache->save($item);

        return $buckets;
    }

    /**
     * @return array{head: list<TimelineEntry>, heart: list<TimelineEntry>, base: list<TimelineEntry>, unknown: list<TimelineEntry>}
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

        return sprintf(
            'match.timeline.%s.%s',
            substr(hash('xxh3', implode(',', $compoundIds)), 0, 16),
            $ctx->signatureHash(),
        );
    }
}
