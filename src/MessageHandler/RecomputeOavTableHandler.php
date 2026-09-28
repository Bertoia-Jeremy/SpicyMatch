<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\OdtMatrix;
use App\Exception\Match\OavRebuildFailedException;
use App\Message\RecomputeOavTableMessage;
use App\Service\Match\MortarProfileBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final readonly class RecomputeOavTableHandler
{
    private const string REBUILD_LOCK = 'spicymatch_oav_rebuild';

    private const int LOCK_WAIT_SECONDS = 0;

    private const int MAX_REBUILD_ATTEMPTS = 3;

    private const int RETRY_DELAY_MS = 60_000;

    public function __construct(
        private Connection $connection,
        private MortarProfileBuilder $mortarProfileBuilder,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        #[Target('oavLogger')]
        private LoggerInterface $oavLogger,
    ) {
    }

    public function __invoke(RecomputeOavTableMessage $message): bool
    {
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
        } catch (Exception $e) {
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
        $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_tmp');
        $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_old');

        $this->connection->executeStatement('CREATE TABLE spice_active_compound_tmp LIKE spice_active_compound');

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

            try {
                $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_tmp');
            } catch (\Throwable) {
            }

            $this->logger->error('[OAV] Rebuild annulé — rollback effectué', [
                'reason' => $reason,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->connection->executeStatement(
            'RENAME TABLE spice_active_compound TO spice_active_compound_old,
                          spice_active_compound_tmp TO spice_active_compound'
        );

        $this->connection->executeStatement('DROP TABLE IF EXISTS spice_active_compound_old');

        $this->mortarProfileBuilder->invalidateAll();
    }

    private function acquireRebuildLock(string $reason): bool
    {
        $acquired = $this->connection->fetchOne('SELECT GET_LOCK(?, ?)', [
            self::REBUILD_LOCK,
            self::LOCK_WAIT_SECONDS,
        ]);

        if ($acquired === null) {
            $this->oavLogger->error('[OAV] Rebuild abandonné — GET_LOCK a renvoyé NULL (erreur serveur MariaDB)', [
                'reason' => $reason,
                'lock' => self::REBUILD_LOCK,
            ]);

            return false;
        }

        if ((string) $acquired !== '1') {
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
