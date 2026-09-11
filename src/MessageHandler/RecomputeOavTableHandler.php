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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

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
    private const REBUILD_LOCK = 'spicymatch_oav_rebuild';

    private const LOCK_WAIT_SECONDS = 0;

    private const MAX_REBUILD_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 60_000;

    public function __construct(
        private readonly Connection $connection,
        private readonly MortarProfileBuilder $mortarProfileBuilder,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $oavLogger,
    ) {
    }

    public function __invoke(RecomputeOavTableMessage $message): bool
    {
        // Sanitize reason pour éviter les log injections (CRLF)
        $reason = preg_replace('/[\r\n]/', ' ', $message->reason) ?? 'manual';

        $start = microtime(true);

        try {
            if (! $this->acquireRebuildLock($reason)) {
                $this->scheduleRetry($message, $reason);

                return false;
            }

            $this->logger->info('[OAV] Début du rebuild spice_active_compound (toutes matrices)', [
                'reason' => $reason,
            ]);

            try {
                $this->doRebuild($reason);
            } finally {
                $this->releaseRebuildLock();
            }
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

        return true;
    }

    private function scheduleRetry(RecomputeOavTableMessage $message, string $reason): void
    {
        if ($message->attempt >= self::MAX_REBUILD_ATTEMPTS) {
            $this->oavLogger->error('[OAV] Rebuild non re-planifié — tentatives épuisées, shadow table potentiellement périmée', [
                'reason' => $reason,
                'attempt' => $message->attempt,
                'max_attempts' => self::MAX_REBUILD_ATTEMPTS,
            ]);

            return;
        }

        $retry = $message->nextAttempt();
        $this->messageBus->dispatch($retry, [new DelayStamp(self::RETRY_DELAY_MS)]);

        $this->oavLogger->info('[OAV] Rebuild re-planifié après abandon', [
            'reason' => $reason,
            'attempt' => $retry->attempt,
            'delay_ms' => self::RETRY_DELAY_MS,
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

        $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_old');

        // ── Invalide le cache des profils mortier (scores périmés sur toutes matrices) ──
        $this->mortarProfileBuilder->invalidateAll();
    }

    private function acquireRebuildLock(string $reason): bool
    {
        $acquired = $this->connection->fetchOne('SELECT GET_LOCK(?, ?)', [
            self::REBUILD_LOCK,
            self::LOCK_WAIT_SECONDS,
        ]);

        if (null === $acquired) {
            $this->oavLogger->error('[OAV] Rebuild abandonné — GET_LOCK a renvoyé NULL (erreur serveur MariaDB)', [
                'reason' => $reason,
                'lock' => self::REBUILD_LOCK,
            ]);

            return false;
        }

        if ('1' !== (string) $acquired) {
            $this->oavLogger->info('[OAV] Rebuild abandonné — verrou détenu par un rebuild concurrent', [
                'reason' => $reason,
            ]);

            return false;
        }

        return true;
    }

    private function releaseRebuildLock(): void
    {
        $this->connection->executeStatement('SELECT RELEASE_LOCK(?)', [self::REBUILD_LOCK]);
    }
}
