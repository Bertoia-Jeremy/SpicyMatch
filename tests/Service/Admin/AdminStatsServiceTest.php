<?php

declare(strict_types=1);

namespace App\Tests\Service\Admin;

use App\Service\Admin\AdminStatsService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * AdminStatsService — powers the admin gamification dashboard.
 * Uses raw DBAL queries; we mock the Connection and assert on the
 * shape of the returned arrays (not the exact SQL strings).
 */
#[AllowMockObjectsWithoutExpectations]
final class AdminStatsServiceTest extends TestCase
{
    private Connection&MockObject $connection;
    private AdminStatsService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->service = new AdminStatsService($this->connection);
    }

    public function testAchievementUnlockRateReturnsEmptyWhenNoUsers(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(0);
        $this->connection->expects(self::never())->method('fetchAllAssociative');

        self::assertSame([], $this->service->achievementUnlockRate());
    }

    public function testAchievementUnlockRateShapesRows(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(10); // 10 total users
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'slug' => 'first-match',
                    'name' => 'Premier mélange',
                    'rarity' => 'common',
                    'unlocks' => 8,
                ],
                [
                    'slug' => 'rare-one',
                    'name' => 'Rare',
                    'rarity' => 'rare',
                    'unlocks' => 2,
                ],
            ]);

        $result = $this->service->achievementUnlockRate();
        self::assertCount(2, $result);
        self::assertSame('first-match', $result[0]['slug']);
        self::assertSame(80.0, $result[0]['unlock_rate']); // 8/10 × 100
        self::assertSame(20.0, $result[1]['unlock_rate']); // 2/10 × 100
    }

    public function testSessionsPerModePerDayCasts(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'day' => '2026-04-20',
                    'game_mode' => 'intrus',
                    'count' => '12',
                ],
                [
                    'day' => '2026-04-21',
                    'game_mode' => 'chrono',
                    'count' => '7',
                ],
            ]);

        $result = $this->service->sessionsPerModePerDay(30);
        self::assertCount(2, $result);
        self::assertSame(12, $result[0]['count']);
        self::assertSame('intrus', $result[0]['game_mode']);
    }

    public function testXpPerDayComputesAverage(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'day' => '2026-04-20',
                    'total_xp' => '100',
                    'active_users' => '10',
                ],
                [
                    'day' => '2026-04-21',
                    'total_xp' => '0',
                    'active_users' => '0',
                ],
            ]);

        $result = $this->service->xpPerDay(30);
        self::assertSame(10.0, $result[0]['avg_xp_per_user']);
        self::assertSame(0.0, $result[1]['avg_xp_per_user']); // division by zero guarded
    }

    public function testAnomaliesReturnsEmptyWhenBelowThreshold(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);
        self::assertSame([], $this->service->anomalies(10));
    }

    public function testAnomaliesShapesRows(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'user_id' => 42,
                    'username' => 'cheater',
                    'flagged_day' => '2026-04-20',
                    'sessions' => '15',
                    'total_xp' => '800',
                ],
            ]);

        $result = $this->service->anomalies(10);
        self::assertCount(1, $result);
        self::assertSame(42, $result[0]['user_id']);
        self::assertSame(15, $result[0]['sessions']);
        self::assertStringContainsString('15 sessions', $result[0]['reason']);
    }
}
