<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Repository\SpicesRepository;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;

/**
 * Adaptateur haut niveau — combine MatchPipeline (veto OAV + Tanimoto) avec
 * l'enrichissement des données d'affichage (nom, image, groupe, type).
 *
 * Remplace CompatibilityScoreService pour les 3 consumers UI/éducation :
 *   - SpicyMatch (Lab Live Component)
 *   - QcmQuestionGenerator (mode QCM)
 *   - AcademyManager (Survival, Intrus)
 *
 * Format de retour compatible avec l'ancien CompatibilityScoreService,
 * sans les champs dépréciés mainCompoundsCount/secondaryCompoundsCount/alchemyFlavorsCount
 * (non affichés dans les templates, calculés par l'ancienne formule Jaccard).
 *
 * Rétrocompatibilité : `findCompatible($mortar, $limit)` sans contexte → défaut air.
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §3 + §4.1
 */
class CompatibleSpiceFinder
{
    public function __construct(
        private readonly MatchPipeline $matchPipeline,
        private readonly SpicesRepository $spicesRepository,
    ) {
    }

    /**
     * Trouve les épices compatibles avec le mortier, triées par score OAV Tanimoto décroissant.
     *
     * @param int             $limit Nombre maximum de résultats (défaut 100)
     * @param CulinaryContext $ctx   Contexte culinaire — matrice ODT (défaut: air)
     *
     * @return list<array{id: int, name: string, file: ?string, agId: ?int, color: ?string, groupName: ?string, stId: ?int, typeName: ?string, score: int}>
     */
    public function findCompatible(
        MortarIds $mortar,
        int $limit = 100,
        CulinaryContext $ctx = new CulinaryContext(),
    ): array {
        $pipelineResults = $this->matchPipeline->run($mortar, $limit, $ctx);

        if ($pipelineResults === []) {
            return [];
        }

        // scoreMap : id → score (issu du pipeline)
        $scoreMap = array_column($pipelineResults, 'score', 'id');

        // Enrichissement : une seule requête SQL pour les données d'affichage
        $enriched = $this->spicesRepository->findEnrichedByIds(array_keys($scoreMap));

        $results = [];
        foreach ($enriched as $row) {
            $id = (int) $row['id'];

            $results[] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'file' => isset($row['file']) ? (string) $row['file'] : null,
                'agId' => isset($row['agId']) ? (int) $row['agId'] : null,
                'color' => isset($row['color']) ? (string) $row['color'] : null,
                'groupName' => isset($row['groupName']) ? (string) $row['groupName'] : null,
                'stId' => isset($row['stId']) ? (int) $row['stId'] : null,
                'typeName' => isset($row['typeName']) ? (string) $row['typeName'] : null,
                'score' => $scoreMap[$id] ?? 0,
            ];
        }

        // Trier par score décroissant (restitue l'ordre du pipeline)
        usort($results, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $results;
    }
}
