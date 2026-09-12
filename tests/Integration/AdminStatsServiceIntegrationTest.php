<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\Admin\AdminStatsService;
use App\Tests\Support\IntegrationTestCase;
use Doctrine\DBAL\Connection;

final class AdminStatsServiceIntegrationTest extends IntegrationTestCase
{
    private AdminStatsService $stats;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stats = static::getContainer()->get(AdminStatsService::class);
        $this->connection = $this->em->getConnection();
    }

    public function testUnlockedByRarityCountsRealUnlocks(): void
    {
        $this->connection->beginTransaction();

        try {
            $before = $this->stats->unlockedByRarity();

            $userId = $this->insertUser('rarity_tester');
            $progressionId = $this->insertUserProgression($userId);
            $commonId = $this->insertAchievement('rarity-common', 'common');
            $legendaryId = $this->insertAchievement('rarity-legendary', 'legendary');
            $this->insertUserAchievement($progressionId, $commonId);
            $this->insertUserAchievement($progressionId, $legendaryId);

            $after = $this->stats->unlockedByRarity();

            self::assertSame((int) ($before['common'] ?? 0) + 1, $after['common']);
            self::assertSame((int) ($before['legendary'] ?? 0) + 1, $after['legendary']);
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testOnboardingCompletionByStepUsesFindInSet(): void
    {
        $this->connection->beginTransaction();

        try {
            $before = $this->stats->onboardingCompletionByStep();

            $this->insertUser('onboarding_partial', 'welcome,spices');
            $this->insertUser('onboarding_no_lab', 'welcome,spiceswithsuffix');
            $this->insertUser('onboarding_null', null);

            $after = $this->stats->onboardingCompletionByStep();

            self::assertSame((int) ($before['welcome']['seen'] ?? 0) + 2, $after['welcome']['seen']);
            self::assertSame(
                (int) ($before['spices']['seen'] ?? 0) + 1,
                $after['spices']['seen'],
                'FIND_IN_SET ne doit matcher que la clé exacte spices, pas spiceswithsuffix',
            );
            self::assertSame((int) ($before['lab']['seen'] ?? 0), $after['lab']['seen']);
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testAchievementProgressCompletionRateCapsAt100Percent(): void
    {
        $this->connection->beginTransaction();

        try {
            $userId = $this->insertUser('progress_tester');
            $achievementId = $this->insertAchievement('overshoot-achievement', 'common', triggerValue: 10);
            $this->insertAchievementProgress($userId, $achievementId, 25);

            $result = $this->stats->achievementProgressCompletionRate();

            $row = array_values(array_filter(
                $result['perAchievement'],
                static fn (array $r): bool => $r['slug'] === 'overshoot-achievement',
            ))[0];

            self::assertSame(100.0, $row['avg_pct']);
        } finally {
            $this->connection->rollBack();
        }
    }

    private function insertUser(string $prefix, ?string $onboardingState = null): int
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $username = $prefix . '_' . bin2hex(random_bytes(4));

        $this->connection->insert('users', [
            'username' => $username,
            'mail' => $username . '@example.test',
            'password' => 'hash',
            'roles' => '[]',
            'created_at' => $now,
            'updated_at' => $now,
            'onboarding_state' => $onboardingState,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertUserProgression(int $userId): int
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->insert('user_progression', [
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertAchievement(string $slug, string $rarity, int $triggerValue = 1): int
    {
        $this->connection->insert('achievement', [
            'slug' => $slug,
            'name' => $slug,
            'description' => 'Test achievement',
            'icon' => 'fa-star',
            'trigger_type' => 'n_matches',
            'trigger_value' => $triggerValue,
            'xp_reward' => 10,
            'rarity' => $rarity,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function insertUserAchievement(int $userProgressionId, int $achievementId): void
    {
        $this->connection->insert('user_achievement', [
            'user_progression_id' => $userProgressionId,
            'achievement_id' => $achievementId,
            'unlocked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    private function insertAchievementProgress(int $userId, int $achievementId, int $progress): void
    {
        $this->connection->insert('achievement_progress', [
            'user_id' => $userId,
            'achievement_id' => $achievementId,
            'progress' => $progress,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}
