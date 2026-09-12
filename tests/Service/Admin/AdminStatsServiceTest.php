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

    public function testGetUserStatsShape(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(100, 5, 42, 7.3);

        $stats = $this->service->getUserStats();
        self::assertSame(100, $stats['totalUsers']);
        self::assertSame(5, $stats['newUsers']);
        self::assertSame(42, $stats['activeUsers']);
        self::assertSame(7.3, $stats['avgLevel']);
    }

    public function testGetGamificationStatsComputesRate(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(50, 10, 5); // 50 unlocks, 10 users, 5 achievements → 50/(10*5)=100%
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'bucket' => '0',
                    'cnt' => '3',
                ],
                [
                    'bucket' => '5',
                    'cnt' => '2',
                ],
            ]);

        $stats = $this->service->getGamificationStats();
        self::assertSame(50, $stats['totalUnlocked']);
        self::assertSame(100.0, $stats['unlockRate']);
        self::assertSame([
            0 => 3,
            5 => 2,
        ], $stats['levelDistribution']);
    }

    public function testGetGamificationStatsGuardsDivisionByZero(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(0, 0, 0);
        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);

        $stats = $this->service->getGamificationStats();
        self::assertSame(0.0, $stats['unlockRate']);
        self::assertSame([], $stats['levelDistribution']);
    }

    public function testGetSpiceStatsShape(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn(
                [[
                    'name' => 'Poivre',
                    'views' => '42',
                ]],
                [[
                    'name' => 'Curcuma',
                    'uses' => '7',
                ]],
                [[
                    'name' => 'Monoterpènes',
                    'cnt' => '120',
                ]],
            );

        $stats = $this->service->getSpiceStats();
        self::assertSame('Poivre', $stats['topViewed'][0]['name']);
        self::assertSame(42, $stats['topViewed'][0]['views']);
        self::assertSame('Curcuma', $stats['topInMatches'][0]['name']);
        self::assertSame(7, $stats['topInMatches'][0]['uses']);
        self::assertSame('Monoterpènes', $stats['groupPopularity'][0]['name']);
        self::assertSame(120, $stats['groupPopularity'][0]['cnt']);
    }

    public function testGetEducationStatsShape(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(150, 78.5);

        $stats = $this->service->getEducationStats();
        self::assertSame(150, $stats['totalGames']);
        self::assertSame(78.5, $stats['avgAccuracy']);
    }

    public function testUnlockedByRarityShapesRows(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'rarity' => 'common',
                    'cnt' => '8',
                ],
                [
                    'rarity' => 'legendary',
                    'cnt' => '1',
                ],
            ]);

        $result = $this->service->unlockedByRarity();
        self::assertSame([
            'common' => 8,
            'rare' => 0,
            'epic' => 0,
            'legendary' => 1,
        ], $result);
    }

    public function testTopViewedSpicesCastsAndScopesToWindow(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'name' => 'Poivre',
                    'views' => '15',
                ],
            ]);

        $result = $this->service->topViewedSpices(7);
        self::assertSame('Poivre', $result[0]['name']);
        self::assertSame(15, $result[0]['views']);
    }

    public function testGameModeDistributionCasts(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'game_mode' => 'qcm',
                    'count' => '30',
                ],
                [
                    'game_mode' => 'intrus',
                    'count' => '9',
                ],
            ]);

        $result = $this->service->gameModeDistribution(30);
        self::assertSame('qcm', $result[0]['game_mode']);
        self::assertSame(30, $result[0]['count']);
    }

    public function testAchievementProgressCompletionRateGuardsDivisionByZero(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(0.0);
        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);

        $result = $this->service->achievementProgressCompletionRate();
        self::assertSame(0.0, $result['globalAvg']);
        self::assertSame([], $result['perAchievement']);
    }

    public function testAchievementProgressCompletionRateComputesAverage(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(62.5);
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'slug' => 'ten-matches',
                    'name' => '10 mélanges',
                    'avg_pct' => '75.333',
                ],
            ]);

        $result = $this->service->achievementProgressCompletionRate();
        self::assertSame(62.5, $result['globalAvg']);
        self::assertSame('ten-matches', $result['perAchievement'][0]['slug']);
        self::assertSame(75.3, $result['perAchievement'][0]['avg_pct']);
    }

    public function testGetEducationStatsBreakdownShape(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn(
                [[
                    'game_mode' => 'qcm',
                    'sessions' => '20',
                    'avg_accuracy' => '80.5',
                ]],
                [[
                    'difficulty' => 'easy',
                    'sessions' => '15',
                    'avg_accuracy' => '90.1',
                ]],
            );
        $this->connection->method('fetchAssociative')
            ->willReturn([
                'live_component' => '40',
                'qcm' => '12',
            ]);

        $stats = $this->service->getEducationStatsBreakdown();
        self::assertSame('qcm', $stats['byMode'][0]['game_mode']);
        self::assertSame(20, $stats['byMode'][0]['sessions']);
        self::assertSame(80.5, $stats['byMode'][0]['avg_accuracy']);
        self::assertSame('easy', $stats['byDifficulty'][0]['difficulty']);
        self::assertSame(90.1, $stats['byDifficulty'][0]['avg_accuracy']);
        self::assertSame(40, $stats['liveComponentAdoption']['live_component']);
        self::assertSame(12, $stats['liveComponentAdoption']['qcm']);
    }

    public function testOnboardingCompletionByStepReturnsEmptyWhenNoUsers(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(0);

        self::assertSame([], $this->service->onboardingCompletionByStep());
    }

    public function testOnboardingCompletionByStepComputesRatePerKey(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(10);
        $this->connection->method('fetchAssociative')
            ->willReturn([
                'welcome' => '10',
                'spices' => '3',
                'lab' => '8',
                'academy' => '5',
            ]);

        $result = $this->service->onboardingCompletionByStep();
        self::assertSame([
            'welcome' => [
                'seen' => 10,
                'rate' => 100.0,
            ],
            'spices' => [
                'seen' => 3,
                'rate' => 30.0,
            ],
            'lab' => [
                'seen' => 8,
                'rate' => 80.0,
            ],
            'academy' => [
                'seen' => 5,
                'rate' => 50.0,
            ],
        ], $result);
    }

    public function testActiveReadingStreaksShape(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(4);
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'username' => 'epicier42',
                    'streak' => '9',
                ],
            ]);

        $result = $this->service->activeReadingStreaks();
        self::assertSame(4, $result['activeCount']);
        self::assertSame('epicier42', $result['top'][0]['username']);
        self::assertSame(9, $result['top'][0]['streak']);
    }

    public function testGetMatchStatsShape(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(200, 3.4);
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                [
                    'date' => '2026-04-20',
                    'count' => '12',
                ],
                [
                    'date' => '2026-04-21',
                    'count' => '8',
                ],
            ]);

        $stats = $this->service->getMatchStats();
        self::assertSame(200, $stats['totalMatches']);
        self::assertSame(3.4, $stats['avgSpicesPerMatch']);
        self::assertCount(2, $stats['recentActivity']);
        self::assertSame(12, $stats['recentActivity'][0]['count']);
    }
}
