<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\OdtMatrix;
use App\Repository\SpicesRepository;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Compare le classement d'un mortier dans les 3 matrices ODT (air/water/oil)
 * en conservant les autres paramètres du contexte (gras, cuisson, température).
 *
 * Usage : afficher au chef qu'un mortier "moyen en bouillon" peut être "excellent
 * à sec" — éducation au caractère contextuel des arômes.
 *
 * Performance :
 *  - Cache "match.insights.cache" (TTL 1h) sur (mortarIds, ctx_hash) pour éviter
 *    les 3× MatchPipeline à chaque consultation de la page recette.
 *  - Sous le capot, MortarProfileBuilder reste mono-matrice par cache (24h/1h),
 *    le hit ici évite l'overhead de Tanimoto + enrichissement noms.
 */
final readonly class MatrixComparator
{
    public function __construct(
        private MatchPipeline $matchPipeline,
        private SpicesRepository $spicesRepository,
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Pour chaque matrice, retourne les top N candidats classés.
     *
     * @return array<string, list<array{id: int, name: string, score: int}>>
     */
    public function compare(MortarIds $mortar, CulinaryContext $baseCtx, int $limit = 5): array
    {
        $cacheKey = $this->cacheKey('compare', $mortar, $baseCtx, $limit);
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

            $rankings[$matrix->value] = $this->rankFor($mortar, $ctx, $limit);
        }

        $item->set($rankings)
            ->expiresAfter(3600);
        $this->cache->save($item);

        return $rankings;
    }

    /**
     * Construit une vue "grille" : ligne = épice présente dans au moins un top N,
     * colonnes = scores par matrice (0 si absente du top de cette matrice).
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
    private function rankFor(MortarIds $mortar, CulinaryContext $ctx, int $limit): array
    {
        $pipeline = $this->matchPipeline->run($mortar, $limit, $ctx);
        if ($pipeline === []) {
            return [];
        }

        $ids = array_column($pipeline, 'id');
        $names = $this->spicesRepository->findNamesById($ids);

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

    /**
     * Clé déterministe pour le cache. Délègue le hash du contexte au VO
     * (Refactor #1 — source unique de signature partagée avec CookingTimelineBuilder).
     */
    private function cacheKey(string $kind, MortarIds $mortar, CulinaryContext $baseCtx, int $limit): string
    {
        return sprintf(
            'match.insights.%s.%s.%s.l%d',
            $kind,
            substr(hash('xxh3', implode(',', $mortar->sorted())), 0, 16),
            $baseCtx->signatureHash(),
            $limit,
        );
    }
}
