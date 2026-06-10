<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\OdtMatrix;
use App\Repository\SpicesRepository;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Compare le ranking d'un mortier sur les 3 matrices (air/water/oil) à ctx fixe.
 * Cache TTL 1h sur (mortar, ctx, limit, locale) — locale dans la clé car les noms
 * d'épices sont enrichis en sortie.
 */
final readonly class MatrixComparator
{
    public function __construct(
        private MatchPipelineInterface $matchPipeline,
        private SpicesRepository $spicesRepository,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return array<string, list<array{id: int, name: string, score: int}>>
     */
    public function compare(MortarIds $mortar, CulinaryContext $baseCtx, int $limit = 5, ?string $locale = null): array
    {
        $cacheKey = $this->cacheKey('compare', $mortar, $baseCtx, $limit, $locale);
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            $cached = $item->get();
            if (is_array($cached)) {
                /** @var array<string, list<array{id: int, name: string, score: int}>> $cached */
                return $cached;
            }
        }

        $rankings = [];
        foreach (OdtMatrix::cases() as $matrix) {
            $ctx = new CulinaryContext(
                $matrix,
                $baseCtx->fatRatio,
                $baseCtx->waterRatio,
                $baseCtx->cookingTimeMin,
                $baseCtx->temperatureCelsius,
            );

            $rankings[$matrix->value] = $this->rankFor($mortar, $ctx, $limit, $locale);
        }

        $item->set($rankings)
            ->expiresAfter(3600);
        $this->cache->save($item);

        return $rankings;
    }

    /**
     * Vue grille : 1 ligne par épice présente dans au moins un top, scores par matrice (0 si absente).
     *
     * @param array<string, list<array{id: int, name: string, score: int}>> $rankings
     *
     * @return list<array{id: int, name: string, scores: array<string, int>}>
     */
    public function buildGrid(array $rankings): array
    {
        $byId = [];

        foreach ($rankings as $matrix => $list) {
            foreach ($list as $entry) {
                $id = $entry['id'];
                if (! isset($byId[$id])) {
                    $byId[$id] = [
                        'id' => $id,
                        'name' => $entry['name'],
                        'scores' => [
                            'air' => 0,
                            'water' => 0,
                            'oil' => 0,
                        ],
                    ];
                }
                $byId[$id]['scores'][$matrix] = $entry['score'];
            }
        }

        $grid = array_values($byId);

        usort($grid, static fn (array $a, array $b) => max($b['scores']) <=> max($a['scores']));

        return $grid;
    }

    /**
     * @return list<array{id: int, name: string, score: int}>
     */
    private function rankFor(MortarIds $mortar, CulinaryContext $ctx, int $limit, ?string $locale = null): array
    {
        $pipeline = $this->matchPipeline->run($mortar, $limit, $ctx);
        if ($pipeline === []) {
            return [];
        }

        $ids = array_column($pipeline, 'id');
        $names = $this->spicesRepository->findNamesById($ids, $locale);

        $list = [];
        foreach ($pipeline as $row) {
            $list[] = [
                'id' => $row['id'],
                'name' => $names[$row['id']] ?? '?',
                'score' => $row['score'],
            ];
        }

        return $list;
    }

    private function cacheKey(
        string $kind,
        MortarIds $mortar,
        CulinaryContext $baseCtx,
        int $limit,
        ?string $locale = null,
    ): string {
        return sprintf(
            'match.insights.%s.%s.%s.l%d.%s',
            $kind,
            substr(hash('xxh3', implode(',', $mortar->sorted())), 0, 16),
            $baseCtx->signatureHash(),
            $limit,
            $locale ?? 'fr',
        );
    }
}
