<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\ValueObject\Match\MortarIds;
use Doctrine\DBAL\Connection;

/**
 * Confiance = maillon le plus faible (weakest tier) parmi les concentrations du
 * mortier et les ODT de la matrice. 2 requêtes DISTINCT, hors hot-path scoring.
 */
final readonly class MatchConfidenceAssessor implements MatchConfidenceAssessorInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function assess(MortarIds $mortar, OdtMatrix $matrix): DataConfidence
    {
        $spiceIds = $mortar->toArray();
        if ($spiceIds === []) {
            return DataConfidence::PLACEHOLDER;
        }

        $tiers = [...$this->concentrationTiers($spiceIds), ...$this->odtTiers($spiceIds, $matrix)];

        if ($tiers === []) {
            return DataConfidence::PLACEHOLDER;
        }

        return DataConfidence::weakest(...$tiers);
    }

    /**
     * @param list<int> $spiceIds
     *
     * @return list<DataConfidence>
     */
    private function concentrationTiers(array $spiceIds): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT confidence FROM spice_compound_concentration WHERE spice_id IN (:ids)',
            [
                'ids' => $spiceIds,
            ],
            [
                'ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER,
            ],
        );

        return $this->mapTiers($rows);
    }

    /**
     * @param list<int> $spiceIds
     *
     * @return list<DataConfidence>
     */
    private function odtTiers(array $spiceIds, OdtMatrix $matrix): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT o.confidence
             FROM compound_odt o
             WHERE o.matrix = :matrix
               AND o.aromatic_compound_id IN (
                   SELECT DISTINCT c.aromatic_compound_id
                   FROM spice_compound_concentration c
                   WHERE c.spice_id IN (:ids)
               )',
            [
                'matrix' => $matrix->value,
                'ids' => $spiceIds,
            ],
            [
                'matrix' => \Doctrine\DBAL\ParameterType::STRING,
                'ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER,
            ],
        );

        return $this->mapTiers($rows);
    }

    /**
     * @param list<mixed> $rawValues
     *
     * @return list<DataConfidence>
     */
    private function mapTiers(array $rawValues): array
    {
        $tiers = [];
        foreach ($rawValues as $raw) {
            $tier = DataConfidence::tryFrom((string) $raw);
            if ($tier !== null) {
                $tiers[] = $tier;
            }
        }

        return $tiers;
    }
}
