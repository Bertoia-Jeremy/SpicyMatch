<?php

declare(strict_types=1);

namespace App\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('gamification')]
final readonly class GamificationScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->with(
                RecurringMessage::cron('0 3 * * *', new RunCommandMessage('app:gamification:cleanup')),
                RecurringMessage::cron('30 4 * * *', new RunCommandMessage('app:gdpr:purge')),
            )
            ->stateful($this->cache)
        ;
    }
}
