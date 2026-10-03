<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CheckSeoIndexableCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckSeoIndexableCommandTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, int}>
     */
    public static function flags(): iterable
    {
        yield 'indexable' => [true, Command::SUCCESS];
        yield 'blocked' => [false, Command::FAILURE];
    }

    #[DataProvider('flags')]
    public function testExitCodeFollowsIndexableFlag(bool $indexable, int $expected): void
    {
        $tester = new CommandTester(new CheckSeoIndexableCommand($indexable));

        self::assertSame($expected, $tester->execute([]));
    }
}
