<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Repository\SpicesRepository;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adapter UI/éducation : pipeline + enrichissement (nom, image, groupe, type) en une requête.
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §3 + §4.1
 */
class CompatibleSpiceFinder
{
    public function __construct(
        private readonly MatchPipelineInterface $matchPipeline,
        private readonly SpicesRepository $spicesRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<array{id: int, name: string, file: ?string, agId: ?int, color: ?string, groupName: ?string, stId: ?int, typeName: ?string, score: int}>
     */
    public function findCompatible(MortarIds $mortar, int $limit, CulinaryContext $ctx): array
    {
        $pipelineResults = $this->matchPipeline->run($mortar, $limit, $ctx);

        if ([] === $pipelineResults) {
            return [];
        }

        $scoreMap = array_column($pipelineResults, 'score', 'id');
        $locale = $this->requestStack->getCurrentRequest()?->getLocale();
        $enriched = $this->spicesRepository->findEnrichedByIds(array_keys($scoreMap), $locale);

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

        usort($results, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $results;
    }
}
