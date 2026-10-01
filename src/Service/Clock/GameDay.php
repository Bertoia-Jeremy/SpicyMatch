<?php

declare(strict_types=1);

namespace App\Service\Clock;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class GameDay
{
    private \DateTimeZone $timezone;

    public function __construct(
        private ClockInterface $clock,
        #[Autowire('%app.timezone%')]
        string $timezone,
    ) {
        $this->timezone = new \DateTimeZone($timezone);
    }

    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()
            ->setTimezone($this->timezone)
            ->setTime(0, 0);
    }

    public function tomorrow(): \DateTimeImmutable
    {
        return $this->today()
            ->modify('+1 day');
    }

    public static function ordinal(\DateTimeImmutable $day): int
    {
        return intdiv(new \DateTimeImmutable($day->format('Y-m-d'), new \DateTimeZone('UTC'))->getTimestamp(), 86400);
    }
}
