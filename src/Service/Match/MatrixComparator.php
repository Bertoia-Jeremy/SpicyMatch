<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\OdtMatrix;
use App\Repository\SpicesRepository;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;

/**
 * Compare le classement d'un mortier dans les 3 matrices ODT (air/water/oil)
 * en conservant les autres paramètres du contexte (gras, cuisson, température).
 *
 * Usage : afficher au chef qu'un mortier "moyen en bouillon" peut être "excellent
 * à sec" — éducation au caractère contextuel des arômes.
 *
 * Performance : 3 appels à MatchPipeline::run() ; la shadow table est filtrée
 * par matrice et le cache MortarProfileBuilder est par matrice → un mortier
 * récemment visité bénéficie du cache (1h–24h selon matrice).
 */
final readonly class MatrixComparator
{
    public function __construct(
        private MatchPipeline $matchPipeline,
        private SpicesRepository $spicesRepository,
    ) {
    }

    /**
     * Pour chaque matrice, retourne les top N candidats classés.
     *
     * @return array<string, list<array{id: int, name: string, score: int}>>
     */
    public function compare(MortarIds $mortar, CulinaryContext $baseCtx, int $limit = 5): array
    {
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

        // Tri : score max (toutes matrices confondues) décroissant
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
}
