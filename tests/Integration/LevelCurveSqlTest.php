<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Gamification\LevelCurve;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LevelCurveSqlTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function xpProvider(): iterable
    {
        yield 'negative' => [-50];
        yield 'zero' => [0];
        yield 'just below level 2' => [246];

        for ($level = 2; $level <= 80; ++$level) {
            $threshold = LevelCurve::thresholdFor($level);
            yield "level {$level} threshold" => [$threshold];
            yield "level {$level} threshold minus one" => [$threshold - 1];
        }
    }

    #[DataProvider('xpProvider')]
    public function testSqlLevelMatchesPhpCurve(int $xp): void
    {
        $level = $this->connection->fetchOne(
            'SELECT ' . LevelCurve::sqlLevel('t.xp') . ' FROM (SELECT ? AS xp) t',
            [$xp],
            [ParameterType::INTEGER],
        );

        self::assertSame(LevelCurve::levelFor($xp), (int) $level);
    }
}
