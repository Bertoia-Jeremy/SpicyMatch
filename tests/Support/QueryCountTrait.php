<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Bound the number of SQL queries executed by a code block.
 *
 * Guards against N+1 regressions — the main failure mode we can't catch with
 * unit tests alone. Requires a KernelTestCase-style container.
 *
 * Usage:
 *   use QueryCountTrait;
 *
 *   $this->assertQueryCountUnder(5, function () {
 *       $this->handler->__invoke($event);
 *   });
 */
trait QueryCountTrait
{
    /**
     * @param callable(): void $callback
     */
    public function assertQueryCountUnder(int $maxQueries, callable $callback): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();

        $logger = new DebugStack();
        $previous = $connection->getConfiguration()
            ->getSQLLogger();
        $connection->getConfiguration()
            ->setSQLLogger($logger);

        try {
            $callback();
        } finally {
            $connection->getConfiguration()
                ->setSQLLogger($previous);
        }

        $count = \count($logger->queries);
        self::assertLessThan(
            $maxQueries,
            $count,
            sprintf(
                'Expected fewer than %d SQL queries, got %d. Executed queries:%s',
                $maxQueries,
                $count,
                "\n".implode("\n", array_map(
                    static fn (array $q) => '  - '.$q['sql'],
                    array_slice($logger->queries, 0, 20),
                )),
            ),
        );
    }

    /**
     * @param callable(): void $callback
     */
    public function assertExactQueryCount(int $expected, callable $callback): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();

        $logger = new DebugStack();
        $previous = $connection->getConfiguration()
            ->getSQLLogger();
        $connection->getConfiguration()
            ->setSQLLogger($logger);

        try {
            $callback();
        } finally {
            $connection->getConfiguration()
                ->setSQLLogger($previous);
        }

        self::assertCount(
            $expected,
            $logger->queries,
            sprintf('Expected exactly %d SQL queries, got %d.', $expected, \count($logger->queries)),
        );
    }
}
