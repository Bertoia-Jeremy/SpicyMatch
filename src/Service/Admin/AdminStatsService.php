<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Enum\AchievementRarity;
use App\Enum\GameMode;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Read-only service for the admin gamification dashboard.
 * Uses raw DBAL queries for performance — the dashboard aggregates across
 * thousands of rows and the ORM overhead is wasteful here.
 *
 * All queries are scoped by date ranges to keep output bounded.
 */
final class AdminStatsService
{
    /**
     * @var list<string>
     */
    private const ONBOARDING_KEYS = ['welcome', 'spices', 'lab', 'academy'];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Unlock rate per achievement over the whole user base.
     *
     * @return list<array{slug: string, name: string, rarity: string, unlocks: int, unlock_rate: float}>
     */
    public function achievementUnlockRate(): array
    {
        $totalUsers = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
        if ($totalUsers === 0) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative('
            SELECT
                a.slug,
                a.name,
                a.rarity,
                COUNT(ua.id) AS unlocks
            FROM achievement a
            LEFT JOIN user_achievement ua ON ua.achievement_id = a.id
            WHERE a.enabled = 1
            GROUP BY a.id
            ORDER BY unlocks DESC
        ');

        return array_map(
            static fn (array $r): array => [
                'slug' => (string) $r['slug'],
                'name' => (string) $r['name'],
                'rarity' => (string) $r['rarity'],
                'unlocks' => (int) $r['unlocks'],
                'unlock_rate' => round(((int) $r['unlocks'] / $totalUsers) * 100, 1),
            ],
            $rows,
        );
    }

    /**
     * Daily sessions per game mode — last N days.
     *
     * @return list<array{day: string, game_mode: string, count: int}>
     */
    public function sessionsPerModePerDay(int $days = 30): array
    {
        $rows = $this->connection->fetchAllAssociative('
            SELECT
                DATE(started_at) AS day,
                game_mode,
                COUNT(*) AS count
            FROM game_session
            WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                AND finished_at IS NOT NULL
            GROUP BY day, game_mode
            ORDER BY day ASC
        ', [
            'days' => $days,
        ]);

        return array_map(
            static fn (array $r): array => [
                'day' => (string) $r['day'],
                'game_mode' => (string) $r['game_mode'],
                'count' => (int) $r['count'],
            ],
            $rows,
        );
    }

    /**
     * Daily XP totals — last N days, summed across all users.
     *
     * @return list<array{day: string, total_xp: int, avg_xp_per_user: float}>
     */
    public function xpPerDay(int $days = 30): array
    {
        $rows = $this->connection->fetchAllAssociative('
            SELECT
                DATE(gs.started_at) AS day,
                SUM(gs.score) AS total_xp,
                COUNT(DISTINCT gs.user_id) AS active_users
            FROM game_session gs
            WHERE gs.finished_at IS NOT NULL
                AND gs.started_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY day
            ORDER BY day ASC
        ', [
            'days' => $days,
        ]);

        return array_map(
            static fn (array $r): array => [
                'day' => (string) $r['day'],
                'total_xp' => (int) $r['total_xp'],
                'avg_xp_per_user' => (int) $r['active_users'] > 0
                    ? round((int) $r['total_xp'] / (int) $r['active_users'], 1)
                    : 0.0,
            ],
            $rows,
        );
    }

    /**
     * @return array{totalUsers: int, newUsers: int, activeUsers: int, avgLevel: float}
     */
    public function getUserStats(): array
    {
        $totalUsers = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
        $newUsers = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'
        );
        $activeUsers = (int) $this->connection->fetchOne('
            SELECT COUNT(DISTINCT user_id)
            FROM game_session
            WHERE started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ');
        // Level is computed: floor((xp / 100) ** (1 / 1.3)) + 1
        $avgLevel = (float) $this->connection->fetchOne('
            SELECT COALESCE(AVG(FLOOR(POW(GREATEST(xp, 0) / 100, 1 / 1.3)) + 1), 0)
            FROM user_progression
        ');

        return [
            'totalUsers' => $totalUsers,
            'newUsers' => $newUsers,
            'activeUsers' => $activeUsers,
            'avgLevel' => round($avgLevel, 1),
        ];
    }

    /**
     * @return array{totalUnlocked: int, unlockRate: float, levelDistribution: array<int, int>}
     */
    public function getGamificationStats(): array
    {
        $totalUnlocked = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_achievement');
        $totalUsers = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
        $totalAchievements = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM achievement WHERE enabled = 1');
        $unlockRate = ($totalUsers > 0 && $totalAchievements > 0)
            ? round(($totalUnlocked / ($totalUsers * $totalAchievements)) * 100, 1)
            : 0.0;

        $rows = $this->connection->fetchAllAssociative('
            SELECT FLOOR((FLOOR(POW(GREATEST(xp, 0) / 100, 1 / 1.3)) + 1) / 5) * 5 AS bucket, COUNT(*) AS cnt
            FROM user_progression
            GROUP BY bucket
            ORDER BY bucket
        ');
        $levelDistribution = [];
        foreach ($rows as $r) {
            $levelDistribution[(int) $r['bucket']] = (int) $r['cnt'];
        }

        return [
            'totalUnlocked' => $totalUnlocked,
            'unlockRate' => $unlockRate,
            'levelDistribution' => $levelDistribution,
        ];
    }

    /**
     * @return array{
     *     topViewed: list<array{name: string, views: int}>,
     *     topInMatches: list<array{name: string, uses: int}>,
     *     groupPopularity: list<array{name: string, cnt: int}>
     * }
     */
    public function getSpiceStats(): array
    {
        $topViewed = array_map(
            static fn (array $r): array => [
                'name' => (string) $r['name'],
                'views' => (int) $r['views'],
            ],
            $this->connection->fetchAllAssociative('
                SELECT s.name, COUNT(sv.id) AS views
                FROM spices s
                LEFT JOIN spice_view sv ON sv.spice_id = s.id
                GROUP BY s.id
                ORDER BY views DESC
                LIMIT 10
            ')
        );

        $topInMatches = array_map(
            static fn (array $r): array => [
                'name' => (string) $r['name'],
                'uses' => (int) $r['uses'],
            ],
            $this->connection->fetchAllAssociative('
                SELECT s.name, COUNT(*) AS uses
                FROM spices s
                JOIN spicy_match_spices sms ON sms.spices_id = s.id
                GROUP BY s.id
                ORDER BY uses DESC
                LIMIT 10
            ')
        );

        $groupPopularity = array_map(
            static fn (array $r): array => [
                'name' => (string) $r['name'],
                'cnt' => (int) $r['cnt'],
            ],
            $this->connection->fetchAllAssociative('
                SELECT ag.name, COUNT(sv.id) AS cnt
                FROM aromatic_groups ag
                JOIN spices s ON s.aromatic_groups_id = ag.id
                LEFT JOIN spice_view sv ON sv.spice_id = s.id
                GROUP BY ag.id
                ORDER BY cnt DESC
            ')
        );

        return [
            'topViewed' => $topViewed,
            'topInMatches' => $topInMatches,
            'groupPopularity' => $groupPopularity,
        ];
    }

    /**
     * @return array{totalGames: int, avgAccuracy: float}
     */
    public function getEducationStats(): array
    {
        $totalGames = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM game_session WHERE finished_at IS NOT NULL'
        );
        $avgAccuracy = (float) $this->connection->fetchOne('
            SELECT COALESCE(AVG(CASE WHEN total_questions > 0 THEN (correct_answers / total_questions) * 100 ELSE 0 END), 0)
            FROM game_session
            WHERE finished_at IS NOT NULL
        ');

        return [
            'totalGames' => $totalGames,
            'avgAccuracy' => round($avgAccuracy, 1),
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
        $avgSpices = (float) $this->connection->fetchOne('
            SELECT COALESCE(AVG(cnt), 0) FROM (
                SELECT COUNT(*) AS cnt
                FROM spicy_match_spices sms
                JOIN spicy_match sm ON sm.id = sms.spicy_match_id
                GROUP BY sm.id
            ) t
        ');

        $recentActivity = array_map(
            static fn (array $r): array => [
                'date' => (string) $r['date'],
                'count' => (int) $r['count'],
            ],
            $this->connection->fetchAllAssociative('
                SELECT DATE(created_at) AS date, COUNT(*) AS count
                FROM spicy_match_history
                WHERE deleted_at IS NULL
                    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY date
                ORDER BY date ASC
            ')
        );

        return [
            'totalMatches' => $totalMatches,
            'avgSpicesPerMatch' => round($avgSpices, 1),
            'recentActivity' => $recentActivity,
        ];
    }

    /**
     * @return list<array{name: string, views: int}>
     */
    public function topViewedSpices(int $days): array
    {
        $rows = $this->connection->fetchAllAssociative('
            SELECT s.name, COUNT(sv.id) AS views
            FROM spices s
            JOIN spice_view sv ON sv.spice_id = s.id
            WHERE sv.viewed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY s.id
            ORDER BY views DESC
            LIMIT 10
        ', [
            'days' => $days,
        ]);

        return array_map(
            static fn (array $r): array => [
                'name' => (string) $r['name'],
                'views' => (int) $r['views'],
            ],
            $rows,
        );
    }

    /**
     * @return list<array{game_mode: string, count: int}>
     */
    public function gameModeDistribution(int $days = 30): array
    {
        $rows = $this->connection->fetchAllAssociative('
            SELECT game_mode, COUNT(*) AS count
            FROM game_session
            WHERE finished_at IS NOT NULL
                AND started_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY game_mode
            ORDER BY count DESC
        ', [
            'days' => $days,
        ]);

        return array_map(
            static fn (array $r): array => [
                'game_mode' => (string) $r['game_mode'],
                'count' => (int) $r['count'],
            ],
            $rows,
        );
    }

    /**
     * @return array{globalAvg: float, perAchievement: list<array{slug: string, name: string, avg_pct: float}>}
     */
    public function achievementProgressCompletionRate(): array
    {
        $globalAvg = (float) $this->connection->fetchOne('
            SELECT COALESCE(AVG(LEAST(ap.progress / a.trigger_value, 1) * 100), 0)
            FROM achievement_progress ap
            JOIN achievement a ON a.id = ap.achievement_id
            WHERE a.trigger_value > 0
        ');

        $rows = $this->connection->fetchAllAssociative('
            SELECT a.slug, a.name, AVG(LEAST(ap.progress / a.trigger_value, 1) * 100) AS avg_pct
            FROM achievement_progress ap
            JOIN achievement a ON a.id = ap.achievement_id
            WHERE a.trigger_value > 0
            GROUP BY a.id
            ORDER BY avg_pct DESC
        ');

        return [
            'globalAvg' => round($globalAvg, 1),
            'perAchievement' => array_map(
                static fn (array $r): array => [
                    'slug' => (string) $r['slug'],
                    'name' => (string) $r['name'],
                    'avg_pct' => round((float) $r['avg_pct'], 1),
                ],
                $rows,
            ),
        ];
    }

    /**
     * @return array{
     *     byMode: list<array{game_mode: string, sessions: int, avg_accuracy: float}>,
     *     byDifficulty: list<array{difficulty: string, sessions: int, avg_accuracy: float}>,
     *     liveComponentAdoption: array{live_component: int, qcm: int}
     * }
     */
    public function getEducationStatsBreakdown(): array
    {
        $byMode = array_map(
            static fn (array $r): array => [
                'game_mode' => (string) $r['game_mode'],
                'sessions' => (int) $r['sessions'],
                'avg_accuracy' => round((float) $r['avg_accuracy'], 1),
            ],
            $this->connection->fetchAllAssociative('
                SELECT
                    game_mode,
                    COUNT(*) AS sessions,
                    COALESCE(AVG(CASE WHEN total_questions > 0 THEN (correct_answers / total_questions) * 100 ELSE 0 END), 0) AS avg_accuracy
                FROM game_session
                WHERE finished_at IS NOT NULL
                GROUP BY game_mode
                ORDER BY sessions DESC
            ')
        );

        $byDifficulty = array_map(
            static fn (array $r): array => [
                'difficulty' => (string) $r['difficulty'],
                'sessions' => (int) $r['sessions'],
                'avg_accuracy' => round((float) $r['avg_accuracy'], 1),
            ],
            $this->connection->fetchAllAssociative('
                SELECT
                    difficulty,
                    COUNT(*) AS sessions,
                    COALESCE(AVG(CASE WHEN total_questions > 0 THEN (correct_answers / total_questions) * 100 ELSE 0 END), 0) AS avg_accuracy
                FROM game_session
                WHERE finished_at IS NOT NULL
                GROUP BY difficulty
                ORDER BY sessions DESC
            ')
        );

        $liveComponentModes = array_map(
            static fn (GameMode $m): string => $m->value,
            array_filter(GameMode::cases(), static fn (GameMode $m): bool => $m->isLiveComponent()),
        );
        $qcmModes = array_map(
            static fn (GameMode $m): string => $m->value,
            array_filter(GameMode::cases(), static fn (GameMode $m): bool => ! $m->isLiveComponent()),
        );

        $adoptionCounts = $this->connection->fetchAssociative('
            SELECT
                SUM(CASE WHEN game_mode IN (:liveModes) THEN 1 ELSE 0 END) AS live_component,
                SUM(CASE WHEN game_mode IN (:qcmModes) THEN 1 ELSE 0 END) AS qcm
            FROM game_session
            WHERE finished_at IS NOT NULL
        ', [
            'liveModes' => $liveComponentModes,
            'qcmModes' => $qcmModes,
        ], [
            'liveModes' => ArrayParameterType::STRING,
            'qcmModes' => ArrayParameterType::STRING,
        ]) ?: [];
        $liveComponentCount = (int) ($adoptionCounts['live_component'] ?? 0);
        $qcmCount = (int) ($adoptionCounts['qcm'] ?? 0);

        return [
            'byMode' => $byMode,
            'byDifficulty' => $byDifficulty,
            'liveComponentAdoption' => [
                'live_component' => $liveComponentCount,
                'qcm' => $qcmCount,
            ],
        ];
    }

    /**
     * @return array<string, array{seen: int, rate: float}>
     */
    public function onboardingCompletionByStep(): array
    {
        $totalUsers = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL');
        if ($totalUsers === 0) {
            return [];
        }

        $selects = [];
        $params = [];
        foreach (self::ONBOARDING_KEYS as $key) {
            $selects[] = sprintf(
                'SUM(CASE WHEN FIND_IN_SET(:%1$s, onboarding_state) > 0 THEN 1 ELSE 0 END) AS %1$s',
                $key
            );
            $params[$key] = $key;
        }

        $row = $this->connection->fetchAssociative(
            sprintf('SELECT %s FROM users WHERE deleted_at IS NULL', implode(', ', $selects)),
            $params
        ) ?: [];

        $result = [];
        foreach (self::ONBOARDING_KEYS as $key) {
            $seen = (int) ($row[$key] ?? 0);
            $result[$key] = [
                'seen' => $seen,
                'rate' => round(($seen / $totalUsers) * 100, 1),
            ];
        }

        return $result;
    }

    /**
     * @return array{activeCount: int, top: list<array{username: string, streak: int}>}
     */
    public function activeReadingStreaks(): array
    {
        $activeCount = (int) $this->connection->fetchOne('
            SELECT COUNT(*) FROM user_progression WHERE current_reading_streak > 0
        ');

        $rows = $this->connection->fetchAllAssociative('
            SELECT u.username, up.current_reading_streak AS streak
            FROM user_progression up
            JOIN users u ON u.id = up.user_id
            WHERE up.current_reading_streak > 0 AND u.deleted_at IS NULL
            ORDER BY up.current_reading_streak DESC
            LIMIT 10
        ');

        return [
            'activeCount' => $activeCount,
            'top' => array_map(
                static fn (array $r): array => [
                    'username' => (string) $r['username'],
                    'streak' => (int) $r['streak'],
                ],
                $rows,
            ),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function unlockedByRarity(): array
    {
        $rows = $this->connection->fetchAllAssociative('
            SELECT a.rarity, COUNT(ua.id) AS cnt
            FROM user_achievement ua
            JOIN achievement a ON a.id = ua.achievement_id
            GROUP BY a.rarity
        ');

        $counts = [];
        foreach ($rows as $r) {
            $counts[(string) $r['rarity']] = (int) $r['cnt'];
        }

        $result = [];
        foreach (AchievementRarity::cases() as $rarity) {
            $result[$rarity->value] = $counts[$rarity->value] ?? 0;
        }

        return $result;
    }

    /**
     * Users with suspicious activity: > threshold sessions in a single day.
     * Simple heuristic — spots bot behavior / exploit attempts.
     *
     * @return list<array{user_id: int, username: string, flagged_day: ?string, sessions: int, total_xp: int, reason: string}>
     */
    public function anomalies(int $sessionThreshold = 10): array
    {
        $rows = $this->connection->fetchAllAssociative('
            SELECT
                u.id AS user_id,
                u.username,
                DATE(gs.started_at) AS flagged_day,
                COUNT(*) AS sessions,
                SUM(gs.score) AS total_xp
            FROM users u
            JOIN game_session gs ON gs.user_id = u.id
            WHERE gs.finished_at IS NOT NULL
                AND gs.started_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY u.id, DATE(gs.started_at)
            HAVING sessions > :threshold
            ORDER BY sessions DESC
            LIMIT 50
        ', [
            'threshold' => $sessionThreshold,
        ]);

        return array_map(
            static fn (array $r): array => [
                'user_id' => (int) $r['user_id'],
                'username' => (string) $r['username'],
                'flagged_day' => $r['flagged_day'] !== null ? (string) $r['flagged_day'] : null,
                'sessions' => (int) $r['sessions'],
                'total_xp' => (int) $r['total_xp'],
                'reason' => sprintf('%d sessions le %s', (int) $r['sessions'], (string) $r['flagged_day']),
            ],
            $rows,
        );
    }
}
