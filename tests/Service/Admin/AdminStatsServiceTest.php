<?php

declare(strict_types=1);

namespace App\Tests\Service\Admin;

use App\Service\Admin\AdminStatsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AdminStatsServiceTest extends TestCase
{
    private Connection&MockObject $connection;
    private AdminStatsService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->service = new AdminStatsService($this->connection);
    }

    public function testGetUserStatsReturnsCorrectStructure(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(42, 15, 5, 8.3);

        $result = $this->service->getUserStats();

        self::assertArrayHasKey('totalUsers', $result);
        self::assertArrayHasKey('activeUsers', $result);
        self::assertArrayHasKey('newUsers', $result);
        self::assertArrayHasKey('avgLevel', $result);
        self::assertSame(42, $result['totalUsers']);
        self::assertSame(15, $result['activeUsers']);
        self::assertSame(5, $result['newUsers']);
        self::assertSame(8.3, $result['avgLevel']);
    }

    public function testGetGamificationStatsReturnsCorrectStructure(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(20, 50, 10);

        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'level_bucket' => 0,
                    'cnt' => 5,
                ],
                [
                    'level_bucket' => 5,
                    'cnt' => 3,
                ],
            ]);

        $result = $this->service->getGamificationStats();

        self::assertArrayHasKey('totalAchievements', $result);
        self::assertArrayHasKey('totalUnlocked', $result);
        self::assertArrayHasKey('unlockRate', $result);
        self::assertArrayHasKey('levelDistribution', $result);
        self::assertSame(20, $result['totalAchievements']);
        self::assertSame(50, $result['totalUnlocked']);
        self::assertSame(25.0, $result['unlockRate']); // 50 / (10 * 20) * 100
        self::assertSame([
            0 => 5,
            5 => 3,
        ], $result['levelDistribution']);
    }

    public function testGetEducationStatsReturnsCorrectStructure(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(30, 75.5);

        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);

        $result = $this->service->getEducationStats();

        self::assertArrayHasKey('totalGames', $result);
        self::assertArrayHasKey('avgAccuracy', $result);
        self::assertArrayHasKey('byMode', $result);
        self::assertSame(30, $result['totalGames']);
        self::assertSame(75.5, $result['avgAccuracy']);
    }

    public function testGetMatchStatsReturnsCorrectStructure(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(100, 3.2);

        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'date' => '2026-03-19',
                    'count' => 5,
                ],
            ]);

        $result = $this->service->getMatchStats();

        self::assertArrayHasKey('totalMatches', $result);
        self::assertArrayHasKey('avgSpicesPerMatch', $result);
        self::assertArrayHasKey('recentActivity', $result);
        self::assertSame(100, $result['totalMatches']);
        self::assertSame(3.2, $result['avgSpicesPerMatch']);
        self::assertCount(1, $result['recentActivity']);
    }

    public function testGetSpiceStatsReturnsCorrectStructure(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    [
                        'name' => 'Cannelle',
                        'views' => 50,
                    ],
                ],
                [
                    [
                        'name' => 'Cumin',
                        'uses' => 30,
                    ],
                ],
                [
                    [
                        'name' => 'Chaud',
                        'cnt' => 80,
                    ],
                ]
            );

        $result = $this->service->getSpiceStats();

        self::assertArrayHasKey('topViewed', $result);
        self::assertArrayHasKey('topInMatches', $result);
        self::assertArrayHasKey('groupPopularity', $result);
        self::assertSame('Cannelle', $result['topViewed'][0]['name']);
    }
}
