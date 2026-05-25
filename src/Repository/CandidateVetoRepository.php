<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\OdtMatrix;
use App\ValueObject\Match\MortarIds;
use Doctrine\DBAL\Connection;

/**
 * Algorithme 1 : Le Veto (graphe biparti booléen).
 *
 * Filtre les épices candidates qui ne partagent pas au moins un composé aromatique
 * perceptible avec chacune des épices du mortier.
 *
 * Deux modes :
 *  - OAV (données disponibles) : se base sur spice_active_compound (OAV > 1, filtré par matrice)
 *  - Présence (fallback) : se base sur spices_aromatic_compound + secondary_spices_aromatic_compound
 *
 * Implémentation en JOIN + HAVING (plus performant que correlated EXISTS sur MariaDB 10.4).
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §2
 */
class CandidateVetoRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Retourne les IDs des épices candidates ayant passé le veto OAV.
     *
     * Condition : ∀ s_i ∈ mortier : |Π_c* ∩ Π_{s_i}*| ≥ 1
     * (au moins un composé OAV-actif partagé avec chaque épice du mortier, dans la matrice donnée)
     *
     * @return list<int> IDs des candidats survivants
     */
    public function findSurvivors(MortarIds $mortar, OdtMatrix $matrix = OdtMatrix::AIR): array
    {
        $mortarSize = $mortar->count();
        $mortarArr = $mortar->toArray();
        $placeholders = implode(',', array_fill(0, $mortarSize, '?'));

        /**
         * Logique JOIN + HAVING :
         *   - sc : composés OAV-actifs du candidat c, dans la matrice demandée
         *   - sm : composés OAV-actifs du mortier, dans la même matrice, matchant sc
         *
         * COUNT(DISTINCT sm.spice_id) = mortarSize signifie que c partage un pont
         * aromatique perceptible avec CHACUNE des épices du mortier (dans cette matrice).
         *
         * Paramètres positionnels (pas de mix named/positional dans DBAL) :
         *   1. matrix.value  → sc.matrix = ?
         *   2..N. mortarArr  → sm.spice_id IN (...)
         *   N+1. matrix.value → sm.matrix = ?
         *   N+2..2N. mortarArr → NOT IN (...)
         *   2N+1. mortarSize  → HAVING = ?
         */
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
                $matrix->value, // sc.matrix = ?
                ...$mortarArr,  // sm.spice_id IN (...)
                $matrix->value, // sm.matrix = ?
                ...$mortarArr,  // NOT IN (...)
                $mortarSize,    // HAVING COUNT = ?
            ],
        );

        return array_map(static fn (array $row) => (int) $row['id'], $rows);
    }

    /**
     * Mode fallback présence-uniquement (sans données OAV).
     *
     * Utilise les deux tables de composés existantes (primaires + secondaires)
     * traitées à plat (sans pondération) pour le veto booléen.
     * Indépendant de la matrice ODT (données de présence non matricielles).
     *
     * @return list<int>
     */
    public function findSurvivorsWithPresence(MortarIds $mortar): array
    {
        $mortarSize = $mortar->count();
        $mortarArr = $mortar->toArray();
        $placeholders = implode(',', array_fill(0, $mortarSize, '?'));

        /**
         * Sous-requêtes qui combinent composés primaires et secondaires :
         *   all_compounds  : tous les composés du candidat c (primaires + secondaires)
         *   mortar_compounds : tous les composés du mortier (primaires + secondaires)
         */
        $sql = <<<SQL
            SELECT c.id
            FROM spices c
            JOIN (
                SELECT spice_id, aromatic_compound_id FROM spices_aromatic_compound
                UNION DISTINCT
                SELECT spice_id, aromatic_compound_id FROM secondary_spices_aromatic_compound
            ) AS all_compounds ON all_compounds.spice_id = c.id
            JOIN (
                SELECT spice_id, aromatic_compound_id
                FROM spices_aromatic_compound
                WHERE spice_id IN ({$placeholders})
                UNION DISTINCT
                SELECT spice_id, aromatic_compound_id
                FROM secondary_spices_aromatic_compound
                WHERE spice_id IN ({$placeholders})
            ) AS mortar_compounds
                ON mortar_compounds.aromatic_compound_id = all_compounds.aromatic_compound_id
            WHERE c.id NOT IN ({$placeholders})
              AND c.deleted_at IS NULL
            GROUP BY c.id
            HAVING COUNT(DISTINCT mortar_compounds.spice_id) = ?
            SQL;

        // mortarArr apparaît 3 fois : 2× UNION IN + 1× NOT IN
        /** @var list<array{id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            $sql,
            [...$mortarArr, ...$mortarArr, ...$mortarArr, $mortarSize],
        );

        return array_map(static fn (array $row) => (int) $row['id'], $rows);
    }
}
