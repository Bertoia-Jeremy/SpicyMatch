<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purges stale gamification rows that accumulate without being needed long-term.
 *
 * Retention policy:
 *   - pending_gamification_notification : 90 days (already delivered or abandoned)
 *   - processed_gamification_event       : 180 days (idempotency ledger — past this
 *                                           window, Messenger retries cannot reasonably
 *                                           re-deliver the same event).
 *
 * Run daily via the scheduler (see config/packages/scheduler.yaml).
 */
#[AsCommand(
    name: 'app:gamification:cleanup',
    description: 'Purges expired gamification notifications + idempotency ledger entries.',
)]
final class GamificationCleanupCommand extends Command
{
    private const NOTIFICATION_RETENTION_DAYS = 90;
    private const LEDGER_RETENTION_DAYS = 180;

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without executing')
            ->addOption(
                'notification-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Purge delivered notifications older than N days',
                self::NOTIFICATION_RETENTION_DAYS,
            )
            ->addOption(
                'ledger-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Purge processed event ledger entries older than N days',
                self::LEDGER_RETENTION_DAYS,
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $notificationDays = (int) $input->getOption('notification-days');
        $ledgerDays = (int) $input->getOption('ledger-days');

        $notifDeleted = $this->purge(
            'pending_gamification_notification',
            'delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL :days DAY)',
            $notificationDays,
            $dryRun,
        );
        $ledgerDeleted = $this->purge(
            'processed_gamification_event',
            'processed_at < DATE_SUB(NOW(), INTERVAL :days DAY)',
            $ledgerDays,
            $dryRun,
        );

        $this->logger->info('gamification.cleanup.completed', [
            'dry_run' => $dryRun,
            'notifications_deleted' => $notifDeleted,
            'ledger_deleted' => $ledgerDeleted,
        ]);

        $io->success(sprintf(
            '%s: notifications=%d, ledger=%d',
            $dryRun ? 'Dry-run' : 'Purged',
            $notifDeleted,
            $ledgerDeleted,
        ));

        return Command::SUCCESS;
    }

    private function purge(string $table, string $whereClause, int $days, bool $dryRun): int
    {
        if ($days <= 0) {
            return 0;
        }

        $countSql = sprintf('SELECT COUNT(*) FROM %s WHERE %s', $table, $whereClause);
        $count = (int) $this->connection->fetchOne($countSql, [
            'days' => $days,
        ]);

        if ($dryRun || $count === 0) {
            return $count;
        }

        $deleteSql = sprintf('DELETE FROM %s WHERE %s', $table, $whereClause);
        $this->connection->executeStatement($deleteSql, [
            'days' => $days,
        ]);

        return $count;
    }
}
