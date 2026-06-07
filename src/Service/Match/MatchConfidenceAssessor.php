<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\ValueObject\Match\MortarIds;
use Doctrine\DBAL\Connection;

/**
 * Évalue la confiance d'un calcul de compatibilité (Levier 4).
 *
 * Le score Tanimoto repose sur deux couches de données — les concentrations des
 * épices du mortier et les ODT des composés dans la matrice. La confiance du
 * résultat est celle du **maillon le plus faible** (weakest tier) parmi toutes
 * ces données : un seul placeholder suffit à rendre le score indicatif.
 *
 * Sert à exposer un badge qualité honnête à l'UI plutôt qu'un pourcentage net
 * bâti sur des données de démonstration.
 *
 * Coût : 2 requêtes agrégées légères (DISTINCT confidence), hors hot-path du
 * scoring — appelé à la demande par l'UI / l'API.
 */
// Non-final : mocké dans les tests du LiveComponent SpicyMatch (PHPUnit ne double pas les classes final).
readonly class MatchConfidenceAssessor
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Confiance globale d'un mortier dans une matrice donnée.
     *
     * Retourne PLACEHOLDER si aucune donnée (le cas le plus défavorable, honnête).
     */
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
     * Niveaux de confiance distincts des concentrations des épices du mortier.
     *
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
     * Niveaux de confiance distincts des ODT (matrice donnée) des composés du mortier.
     *
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
