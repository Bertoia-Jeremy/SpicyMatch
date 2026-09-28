<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\OdtMatrix;
use App\ValueObject\Match\MortarIds;
use Doctrine\DBAL\Connection;

class CandidateVetoRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<int> IDs des candidats survivants
     */
    public function findSurvivors(MortarIds $mortar, OdtMatrix $matrix): array
    {
        $mortarSize = $mortar->count();
        $mortarArr = $mortar->toArray();
        $placeholders = implode(',', array_fill(0, $mortarSize, '?'));

        $sql = <<<SQL
            SELECT c.id
            FROM spices c
            JOIN spice_active_compound sc
                ON sc.spice_id = c.id
                AND sc.matrix = ?
            JOIN spice_active_compound sm
                ON  sm.aromatic_compound_id = sc.aromatic_compound_id
                AND sm.spice_id IN ({$placeholders})
                AND sm.matrix = ?
            WHERE c.id NOT IN ({$placeholders})
              AND c.deleted_at IS NULL
            GROUP BY c.id
            HAVING COUNT(DISTINCT sm.spice_id) = ?
            SQL;

        /** @var list<array{id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            $sql,
            [
                $matrix->value,
                ...$mortarArr,
                $matrix->value,
                ...$mortarArr,
                $mortarSize,
            ],
        );

        return array_map(static fn (array $row) => (int) $row['id'], $rows);
    }

    /**
     * @return list<int>
     */
    public function findSurvivorsWithPresence(MortarIds $mortar): array
    {
        $mortarSize = $mortar->count();
        $mortarArr = $mortar->toArray();
        $placeholders = implode(',', array_fill(0, $mortarSize, '?'));

        $sql = <<<SQL
            SELECT c.id
            FROM spices c
            JOIN (
                SELECT spices_id AS spice_id, aromatic_compound_id FROM spices_aromatic_compound
                UNION DISTINCT
                SELECT spices_id AS spice_id, aromatic_compound_id FROM secondary_spices_aromatic_compound
            ) AS all_compounds ON all_compounds.spice_id = c.id
            JOIN (
                SELECT spices_id AS spice_id, aromatic_compound_id
                FROM spices_aromatic_compound
                WHERE spices_id IN ({$placeholders})
                UNION DISTINCT
                SELECT spices_id AS spice_id, aromatic_compound_id
                FROM secondary_spices_aromatic_compound
                WHERE spices_id IN ({$placeholders})
            ) AS mortar_compounds
                ON mortar_compounds.aromatic_compound_id = all_compounds.aromatic_compound_id
            WHERE c.id NOT IN ({$placeholders})
              AND c.deleted_at IS NULL
            GROUP BY c.id
            HAVING COUNT(DISTINCT mortar_compounds.spice_id) = ?
            SQL;

        /** @var list<array{id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            $sql,
            [...$mortarArr, ...$mortarArr, ...$mortarArr, $mortarSize],
        );

        return array_map(static fn (array $row) => (int) $row['id'], $rows);
    }
}
