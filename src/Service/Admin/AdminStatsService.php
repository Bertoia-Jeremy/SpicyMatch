<?php

declare(strict_types=1);

namespace App\Service\Admin;

use Doctrine\DBAL\Connection;

class AdminStatsService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array{totalUsers: int, activeUsers: int, newUsers: int, avgLevel: float}
     */
    public function getUserStats(): array
    {
        $totalUsers = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');

        $activeUsers = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND last_login >= :since',
            [
                'since' => (new \DateTimeImmutable('-30 days'))->format('Y-m-d'),
            ]
        );

        $newUsers = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND created_at >= :since',
            [
                'since' => (new \DateTimeImmutable('-7 days'))->format('Y-m-d'),
            ]
        );

        $avgLevel = (float) ($this->connection->fetchOne(
            'SELECT AVG(FLOOR(POW(xp / 100, 1/1.3))) FROM user_progression'
        ) ?? 0);

        return [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'newUsers' => $newUsers,
            'avgLevel' => round($avgLevel, 1),
        ];
    }

    /**
     * @return array{totalAchievements: int, totalUnlocked: int, unlockRate: float, levelDistribution: array<int, int>}
     */
    public function getGamificationStats(): array
    {
        $totalAchievements = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM achievement');

        $totalUnlocked = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_achievement');

        $totalProgressions = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_progression');

        $unlockRate = $totalProgressions > 0 && $totalAchievements > 0
            ? round($totalUnlocked / ($totalProgressions * $totalAchievements) * 100, 1)
            : 0.0;

        // Level distribution (grouped by buckets of 5)
        $rows = $this->connection->fetchAllAssociative(
            'SELECT
                FLOOR(FLOOR(POW(xp / 100, 1/1.3)) / 5) * 5 AS level_bucket,
                COUNT(*) AS cnt
             FROM user_progression
             GROUP BY level_bucket
             ORDER BY level_bucket'
        );

        $levelDistribution = [];
        foreach ($rows as $row) {
            $levelDistribution[(int) $row['level_bucket']] = (int) $row['cnt'];
        }

        return [
            'totalAchievements' => $totalAchievements,
            'totalUnlocked' => $totalUnlocked,
            'unlockRate' => $unlockRate,
            'levelDistribution' => $levelDistribution,
        ];
    }

    /**
     * @return array{topViewed: list<array{name: string, views: int}>, topInMatches: list<array{name: string, uses: int}>, groupPopularity: list<array{name: string, count: int}>}
     */
    public function getSpiceStats(): array
    {
        $topViewed = $this->connection->fetchAllAssociative(
            'SELECT s.name, COUNT(sv.id) AS views
             FROM spice_view sv
             JOIN spices s ON s.id = sv.spice_id
             GROUP BY s.id, s.name
             ORDER BY views DESC
             LIMIT 10'
        );

        $topInMatches = $this->connection->fetchAllAssociative(
            'SELECT s.name, COUNT(*) AS uses
             FROM spicy_match_spices sms
             JOIN spices s ON s.id = sms.spices_id
             GROUP BY s.id, s.name
             ORDER BY uses DESC
             LIMIT 10'
        );

        $groupPopularity = $this->connection->fetchAllAssociative(
            'SELECT ag.name, COUNT(sv.id) AS cnt
             FROM spice_view sv
             JOIN spices s ON s.id = sv.spice_id
             JOIN aromatic_groups ag ON ag.id = s.aromaticGroups
             GROUP BY ag.id, ag.name
             ORDER BY cnt DESC'
        );

        return [
            'topViewed' => $topViewed,
            'topInMatches' => $topInMatches,
            'groupPopularity' => $groupPopularity,
        ];
    }

    /**
     * @return array{totalGames: int, avgAccuracy: float, byMode: list<array{mode: string, count: int, avgScore: float}>}
     */
    public function getEducationStats(): array
    {
        $totalGames = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM game_session WHERE finished_at IS NOT NULL'
        );

        $avgAccuracy = (float) ($this->connection->fetchOne(
            'SELECT AVG(correct_answers / total_questions * 100)
             FROM game_session
             WHERE finished_at IS NOT NULL AND total_questions > 0'
        ) ?? 0);

        $byMode = $this->connection->fetchAllAssociative(
            'SELECT game_mode AS mode, COUNT(*) AS count,
                    AVG(correct_answers / total_questions * 100) AS avgScore
             FROM game_session
             WHERE finished_at IS NOT NULL AND total_questions > 0
             GROUP BY game_mode'
        );

        return [
            'totalGames' => $totalGames,
            'avgAccuracy' => round($avgAccuracy, 1),
            'byMode' => $byMode,
        ];
    }

    /**
     * @return array{totalMatches: int, avgSpicesPerMatch: float, recentActivity: list<array{date: string, count: int}>}
     */
    public function getMatchStats(): array
    {
        $totalMatches = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM spicy_match_history WHERE deleted_at IS NULL'
        );

        $avgSpicesPerMatch = (float) ($this->connection->fetchOne(
            'SELECT AVG(spice_count) FROM (
                SELECT smh.id, COUNT(sms.spices_id) AS spice_count
                FROM spicy_match_history smh
                JOIN spicy_match sm ON sm.id = smh.spicy_match_id
                JOIN spicy_match_spices sms ON sms.spicy_match_id = sm.id
                WHERE smh.deleted_at IS NULL
                GROUP BY smh.id
            ) sub'
        ) ?? 0);

        $recentActivity = $this->connection->fetchAllAssociative(
            'SELECT DATE(smh.created_at) AS date, COUNT(*) AS count
             FROM spicy_match_history smh
             WHERE smh.deleted_at IS NULL AND smh.created_at >= :since
             GROUP BY DATE(smh.created_at)
             ORDER BY date',
            [
                'since' => (new \DateTimeImmutable('-30 days'))->format('Y-m-d'),
            ]
        );

        return [
            'totalMatches' => $totalMatches,
            'avgSpicesPerMatch' => round($avgSpicesPerMatch, 1),
            'recentActivity' => $recentActivity,
        ];
    }
}
