<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Recurring tasks for gamification hygiene.
 *
 * Execute with a scheduler worker:
 *   php bin/console messenger:consume scheduler_gamification --limit=10
 *
 * Deploy a systemd service / cron supervisor to keep the worker alive in prod.
 */
#[AsSchedule('gamification')]
final class GamificationScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->with(
                // Daily 03:00 — purges old notifications + processed-event ledger entries.
                RecurringMessage::cron('0 3 * * *', new RunCommandMessage('app:gamification:cleanup')),
                RecurringMessage::cron('0 4 * * *', new RunCommandMessage('app:purge-expired-consents')),
                RecurringMessage::cron('30 4 * * *', new RunCommandMessage('app:gdpr:purge')),
            )
            ->stateful($this->cache)
        ;
    }
}
