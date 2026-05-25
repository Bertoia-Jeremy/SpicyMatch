<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\OdtMatrix;
use App\Exception\Match\OavRebuildFailedException;
use App\Message\RecomputeOavTableMessage;
use App\Service\Match\MortarProfileBuilder;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Rebuild atomique de spice_active_compound (vue matérialisée OAV) — toutes matrices.
 *
 * Formule : OAV = concentration_ppm / odt_ppm
 * Seuls les composés avec OAV > 1 sont insérés (filtre dans le WHERE).
 * Les 3 matrices (air, water, oil) sont calculées en une seule passe.
 *
 * Stratégie shadow table (zéro downtime) :
 *   DDL (hors transaction — commit implicite MariaDB) :
 *     1. DROP TABLE IF EXISTS spice_active_compound_tmp
 *     2. DROP TABLE IF EXISTS spice_active_compound_old
 *     3. CREATE TABLE spice_active_compound_tmp LIKE spice_active_compound
 *
 *   DML (transaction InnoDB — 3 INSERT atomiques) :
 *     4. BEGIN TRANSACTION
 *     5. INSERT INTO tmp (air) — calcul OAV, filtre OAV > 1
 *     6. INSERT INTO tmp (water)
 *     7. INSERT INTO tmp (oil)
 *     8. COMMIT
 *        → Si erreur : ROLLBACK + DROP tmp (prod table intacte)
 *
 *   DDL atomique :
 *     9. RENAME TABLE spice_active_compound → _old, spice_active_compound_tmp → spice_active_compound
 *    10. DROP TABLE spice_active_compound_old
 *
 * Invariant : la table de production n'est jamais touchée si un INSERT échoue.
 * RENAME TABLE est atomique InnoDB → aucune fenêtre où la table est vide.
 * Les requêtes /api/match concurrentes voient soit l'ancienne, soit la nouvelle table.
 *
 * Après rebuild, invalide le cache des profils mortier (MortarProfileBuilder).
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §5 + §4.5
 */
#[AsMessageHandler]
final class RecomputeOavTableHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MortarProfileBuilder $mortarProfileBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RecomputeOavTableMessage $message): void
    {
        // Sanitize reason pour éviter les log injections (CRLF)
        $reason = preg_replace('/[\r\n]/', ' ', $message->reason) ?? 'manual';

        $this->logger->info('[OAV] Début du rebuild spice_active_compound (toutes matrices)', [
            'reason' => $reason,
        ]);

        $start = microtime(true);

        try {
            $this->doRebuild($reason);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('[OAV] Rebuild échoué — exception DBAL', [
                'reason' => $reason,
                'exception' => $e->getMessage(),
            ]);

            throw OavRebuildFailedException::fromDbalException($e);
        }

        $elapsed = round((microtime(true) - $start) * 1000);
        $this->logger->info('[OAV] Rebuild terminé', [
            'elapsed_ms' => $elapsed,
        ]);
    }

    private function doRebuild(string $reason): void
    {
        // ── Phase DDL — hors transaction (commit implicite MariaDB) ──────────────
        // spice_active_compound_old peut rester si un run précédent a crashé entre
        // l'étape RENAME et l'étape DROP — sans ce DROP, le RENAME lèverait
        // ERROR 1050 "Table already exists" et bloquerait le handler indéfiniment.
        $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_tmp');
        $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_old');

        // Copie structure + index (dont la nouvelle PK avec colonne matrix)
        $this->connection->executeStatement('CREATE TABLE spice_active_compound_tmp LIKE spice_active_compound');

        // ── Phase DML — transaction InnoDB unique sur les 3 INSERT ───────────────
        // Si un INSERT échoue, rollBack() + DROP tmp → prod inchangée.
        // Les DDL restent hors transaction (RENAME/DROP TABLE plus bas).
        $this->connection->beginTransaction();
        try {
            foreach (OdtMatrix::cases() as $matrix) {
                $inserted = $this->connection->executeStatement(
                    <<<SQL
                    INSERT INTO spice_active_compound_tmp (spice_id, aromatic_compound_id, matrix, oav_value)
                    SELECT
                        scc.spice_id,
                        scc.aromatic_compound_id,
                        :matrix,
                        scc.concentration_ppm / NULLIF(odt.odt_ppm, 0) AS oav
                    FROM spice_compound_concentration scc
                    JOIN compound_odt odt
                        ON odt.aromatic_compound_id = scc.aromatic_compound_id
                        AND odt.matrix = :matrix
                        AND odt.odt_ppm > 0
                    WHERE scc.concentration_ppm / NULLIF(odt.odt_ppm, 0) > 1
                    SQL
                    ,
                    [
                        'matrix' => $matrix->value,
                    ],
                );

                $this->logger->info('[OAV] Shadow table — matrice peuplée', [
                    'matrix' => $matrix->value,
                    'rows_inserted' => $inserted,
                    'reason' => $reason,
                ]);
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            // Nettoyage préventif : la tmp contient des données partielles.
            // Hors transaction (DDL) → executeStatement direct, pas de throw sur failure.
            try {
                $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_tmp');
            } catch (\Throwable) {
                // Orpheline tolérée : le prochain run fera DROP IF EXISTS au début.
            }

            $this->logger->error('[OAV] Rebuild annulé — rollback effectué', [
                'reason' => $reason,
                'exception' => $e->getMessage(),
            ]);

            throw $e; // Messenger retentera selon sa politique de retry
        }

        // ── Phase DDL atomique — swap production ─────────────────────────────────
        // RENAME TABLE est atomique InnoDB → aucune fenêtre où la table est vide.
        $this->connection->executeStatement(
            'RENAME TABLE spice_active_compound TO spice_active_compound_old,
                          spice_active_compound_tmp TO spice_active_compound'
        );

        $this->connection->executeStatement('DROP TABLE spice_active_compound_old');

        // ── Invalide le cache des profils mortier (scores périmés sur toutes matrices) ──
        $this->mortarProfileBuilder->invalidateAll();
    }
}
