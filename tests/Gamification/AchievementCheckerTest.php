<?php

declare(strict_types=1);

namespace App\Tests\Gamification;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Gamification\AchievementChecker;
use App\Repository\AchievementRepository;
use App\Repository\AromaticGroupsRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class AchievementCheckerTest extends TestCase
{
    private AchievementRepository&MockObject $repo;
    private AromaticGroupsRepository&MockObject $aromaticGroupsRepo;
    private AchievementChecker $checker;
    private UserProgression $progression;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(AchievementRepository::class);
        $this->aromaticGroupsRepo = $this->createMock(AromaticGroupsRepository::class);
        $this->checker = new AchievementChecker($this->repo, $this->aromaticGroupsRepo);
        $this->progression = new UserProgression();
    }

    // ── Event → trigger mapping ───────────────────────────────────────────────

    public function testUnknownEventReturnsEmpty(): void
    {
        $this->repo->expects(self::never())->method('findByTrigger');

        self::assertSame([], $this->checker->check($this->progression, 'unknown_event', []));
    }

    public function testDefaultEventReturnsEmpty(): void
    {
        $this->repo->expects(self::never())->method('findByTrigger');

        self::assertSame([], $this->checker->check($this->progression, '', []));
    }

    // ── FIRST_MATCH ───────────────────────────────────────────────────────────

    public function testUnlocksFirstMatchWhenTotalMatchesIsOne(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_MATCH, 1);
        $this->stubRepo(AchievementTrigger::FIRST_MATCH, [$achievement]);

        $this->setField('totalMatches', 1);

        $result = $this->checker->check($this->progression, 'match_saved', []);

        self::assertCount(1, $result);
        self::assertSame($achievement, $result[0]);
    }

    public function testDoesNotUnlockFirstMatchWhenTotalMatchesIsZero(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_MATCH, 1);
        $this->stubRepo(AchievementTrigger::FIRST_MATCH, [$achievement]);

        $result = $this->checker->check($this->progression, 'match_saved', []);

        self::assertSame([], $result);
    }

    // ── N_MATCHES ─────────────────────────────────────────────────────────────

    public function testUnlocksNMatchesWhenThresholdReached(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::N_MATCHES, 10);
        $this->stubRepo(AchievementTrigger::N_MATCHES, [$achievement]);

        $this->setField('totalMatches', 10);

        $result = $this->checker->check($this->progression, 'match_saved', []);

        self::assertCount(1, $result);
    }

    public function testDoesNotUnlockNMatchesBelowThreshold(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::N_MATCHES, 10);
        $this->stubRepo(AchievementTrigger::N_MATCHES, [$achievement]);

        $this->setField('totalMatches', 9);

        self::assertSame([], $this->checker->check($this->progression, 'match_saved', []));
    }

    // ── N_SPICES_USED ─────────────────────────────────────────────────────────

    public function testUnlocksNSpicesUsedWhenThresholdReached(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::N_SPICES_USED, 5);
        $this->stubRepo(AchievementTrigger::N_SPICES_USED, [$achievement]);

        $this->setField('uniqueSpicesUsed', 5);

        $result = $this->checker->check($this->progression, 'match_saved', []);

        self::assertCount(1, $result);
    }

    // ── SPICE_READ ────────────────────────────────────────────────────────────

    public function testUnlocksSpiceReadWhenThresholdReached(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::SPICE_READ, 10);
        $this->stubRepo(AchievementTrigger::SPICE_READ, [$achievement]);

        $this->setField('totalSpicesRead', 10);

        $result = $this->checker->check($this->progression, 'spice_read', []);

        self::assertCount(1, $result);
    }

    public function testDoesNotUnlockSpiceReadBelowThreshold(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::SPICE_READ, 10);
        $this->stubRepo(AchievementTrigger::SPICE_READ, [$achievement]);

        $this->setField('totalSpicesRead', 9);

        self::assertSame([], $this->checker->check($this->progression, 'spice_read', []));
    }

    // ── READING_STREAK ────────────────────────────────────────────────────────

    public function testUnlocksReadingStreakWhenLongestStreakReachesThreshold(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::READING_STREAK, 7);
        $this->stubRepo(AchievementTrigger::READING_STREAK, [$achievement]);

        $this->setField('longestReadingStreak', 7);

        $result = $this->checker->check($this->progression, 'spice_read', []);

        self::assertCount(1, $result);
    }

    public function testDoesNotUnlockReadingStreakBelowThreshold(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::READING_STREAK, 7);
        $this->stubRepo(AchievementTrigger::READING_STREAK, [$achievement]);

        $this->setField('longestReadingStreak', 6);

        self::assertSame([], $this->checker->check($this->progression, 'spice_read', []));
    }

    // ── N_FAVORITES ───────────────────────────────────────────────────────────

    public function testUnlocksNFavoritesWhenContextCountReachesThreshold(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::N_FAVORITES, 5);
        $this->stubRepo(AchievementTrigger::N_FAVORITES, [$achievement]);

        $result = $this->checker->check($this->progression, 'favorite_toggled', [
            'favoriteCount' => 5,
        ]);

        self::assertCount(1, $result);
    }

    public function testDoesNotUnlockNFavoritesBelowContextCount(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::N_FAVORITES, 5);
        $this->stubRepo(AchievementTrigger::N_FAVORITES, [$achievement]);

        self::assertSame(
            [],
            $this->checker->check($this->progression, 'favorite_toggled', [
                'favoriteCount' => 4,
            ])
        );
    }

    public function testNFavoritesMissingContextCountTreatedAsZero(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::N_FAVORITES, 1);
        $this->stubRepo(AchievementTrigger::N_FAVORITES, [$achievement]);

        self::assertSame([], $this->checker->check($this->progression, 'favorite_toggled', []));
    }

    // ── EASTER_EGG_FOUND ──────────────────────────────────────────────────────

    public function testUnlocksEasterEggWhenSlugMatches(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::EASTER_EGG_FOUND, 0, 'first_egg');
        $this->stubRepo(AchievementTrigger::EASTER_EGG_FOUND, [$achievement]);

        $result = $this->checker->check($this->progression, 'easter_egg_found', [
            'easterEggSlug' => 'first_egg',
        ]);

        self::assertCount(1, $result);
    }

    public function testDoesNotUnlockEasterEggWhenSlugDoesNotMatch(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::EASTER_EGG_FOUND, 0, 'first_egg');
        $this->stubRepo(AchievementTrigger::EASTER_EGG_FOUND, [$achievement]);

        self::assertSame(
            [],
            $this->checker->check($this->progression, 'easter_egg_found', [
                'easterEggSlug' => 'wrong_slug',
            ])
        );
    }

    public function testDoesNotUnlockEasterEggWhenSlugMissingFromContext(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::EASTER_EGG_FOUND, 0, 'first_egg');
        $this->stubRepo(AchievementTrigger::EASTER_EGG_FOUND, [$achievement]);

        self::assertSame([], $this->checker->check($this->progression, 'easter_egg_found', []));
    }

    // ── FIRST_GAME ──────────────────────────────────────────────────────────

    public function testUnlocksFirstGameWhenGamesCompletedIsOne(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_GAME, 1);
        $this->stubRepo(AchievementTrigger::FIRST_GAME, [$achievement]);

        $result = $this->checker->check($this->progression, 'game_completed', [
            'gamesCompleted' => 1,
        ]);

        self::assertCount(1, $result);
    }

    // ── N_GAMES_COMPLETED ──────────────────────────────────────────────────

    public function testUnlocksNGamesCompletedWhenThresholdReached(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::N_GAMES_COMPLETED, 10);
        $this->stubRepo(AchievementTrigger::N_GAMES_COMPLETED, [$achievement]);

        $result = $this->checker->check($this->progression, 'game_completed', [
            'gamesCompleted' => 10,
        ]);

        self::assertCount(1, $result);
    }

    public function testDoesNotUnlockNGamesCompletedBelowThreshold(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::N_GAMES_COMPLETED, 10);
        $this->stubRepo(AchievementTrigger::N_GAMES_COMPLETED, [$achievement]);

        self::assertSame(
            [],
            $this->checker->check($this->progression, 'game_completed', [
                'gamesCompleted' => 9,
            ])
        );
    }

    // ── FIRST_DISCOVERY ──────────────────────────────────────────────────────

    public function testUnlocksFirstDiscoveryWhenDiscoveriesReachThreshold(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_DISCOVERY, 1);
        $this->stubRepo(AchievementTrigger::FIRST_DISCOVERY, [$achievement]);

        $this->setField('discoveries', 1);

        $result = $this->checker->check($this->progression, 'spice_read', []);

        self::assertCount(1, $result);
    }

    public function testDoesNotUnlockFirstDiscoveryBelowThreshold(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_DISCOVERY, 5);
        $this->stubRepo(AchievementTrigger::FIRST_DISCOVERY, [$achievement]);

        $this->setField('discoveries', 4);

        self::assertSame([], $this->checker->check($this->progression, 'spice_read', []));
    }

    // ── ALL_TERPENES_VISITED ───────────────────────────────────────────────

    public function testAllTerpenesVisitedReturnsFalseWhenStatsNull(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::ALL_TERPENES_VISITED, 1);
        $this->stubRepo(AchievementTrigger::ALL_TERPENES_VISITED, [$achievement]);

        // progression has no user → getUser() returns null → stats null
        self::assertSame([], $this->checker->check($this->progression, 'spice_read', []));
    }

    public function testAllTerpenesVisitedReturnsFalseWhenZeroGroups(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::ALL_TERPENES_VISITED, 1);
        $this->stubRepo(AchievementTrigger::ALL_TERPENES_VISITED, [$achievement]);

        $stats = new \App\Entity\UserStat();
        $user = $this->createMock(\App\Entity\Users::class);
        $user->method('getStats')
            ->willReturn($stats);
        $this->progression->setUser($user);

        $this->aromaticGroupsRepo->method('count')
            ->willReturn(0);

        self::assertSame([], $this->checker->check($this->progression, 'spice_read', []));
    }

    public function testAllTerpenesVisitedReturnsTrueWhenAllGroupsVisited(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::ALL_TERPENES_VISITED, 1);
        $this->stubRepo(AchievementTrigger::ALL_TERPENES_VISITED, [$achievement]);

        $stats = new \App\Entity\UserStat();
        $stats->addVisitedAromaticGroup(1);
        $stats->addVisitedAromaticGroup(2);
        $stats->addVisitedAromaticGroup(3);

        $user = $this->createMock(\App\Entity\Users::class);
        $user->method('getStats')
            ->willReturn($stats);
        $this->progression->setUser($user);

        $this->aromaticGroupsRepo->method('count')
            ->willReturn(3);

        $result = $this->checker->check($this->progression, 'spice_read', []);

        self::assertCount(1, $result);
    }

    public function testAllTerpenesVisitedReturnsFalseWhenNotAllGroupsVisited(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::ALL_TERPENES_VISITED, 1);
        $this->stubRepo(AchievementTrigger::ALL_TERPENES_VISITED, [$achievement]);

        $stats = new \App\Entity\UserStat();
        $stats->addVisitedAromaticGroup(1);
        $stats->addVisitedAromaticGroup(2);

        $user = $this->createMock(\App\Entity\Users::class);
        $user->method('getStats')
            ->willReturn($stats);
        $this->progression->setUser($user);

        $this->aromaticGroupsRepo->method('count')
            ->willReturn(5);

        self::assertSame([], $this->checker->check($this->progression, 'spice_read', []));
    }

    // ── Multiple triggers for same event ────────────────────────────────────

    public function testMatchSavedChecksAllThreeTriggers(): void
    {
        $firstMatch = $this->makeAchievement(AchievementTrigger::FIRST_MATCH, 1);
        $nMatches = $this->makeAchievement(AchievementTrigger::N_MATCHES, 5);
        $nSpices = $this->makeAchievement(AchievementTrigger::N_SPICES_USED, 3);

        $this->repo->method('findByTrigger')
            ->willReturnCallback(fn (AchievementTrigger $t) => match ($t) {
                AchievementTrigger::FIRST_MATCH => [$firstMatch],
                AchievementTrigger::N_MATCHES => [$nMatches],
                AchievementTrigger::N_SPICES_USED => [$nSpices],
                default => [],
            });

        $this->setField('totalMatches', 5);
        $this->setField('uniqueSpicesUsed', 3);

        $result = $this->checker->check($this->progression, 'match_saved', []);

        // All 3 should unlock
        self::assertCount(3, $result);
    }

    // ── Already owned ─────────────────────────────────────────────────────────

    public function testAlreadyOwnedAchievementIsNotReturnedAgain(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_MATCH, 1);
        $this->stubRepo(AchievementTrigger::FIRST_MATCH, [$achievement]);
        $this->progression->unlockAchievement($achievement); // already owned

        $this->setField('totalMatches', 5);

        $result = $this->checker->check($this->progression, 'match_saved', []);

        self::assertSame([], $result);
    }

    // ── unlockAchievement side-effect ─────────────────────────────────────────

    public function testCheckMutatesProgressionByAddingUnlockedAchievement(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_MATCH, 1);
        $this->stubRepo(AchievementTrigger::FIRST_MATCH, [$achievement]);

        $this->setField('totalMatches', 1);

        $this->checker->check($this->progression, 'match_saved', []);

        self::assertTrue($this->progression->hasAchievement($achievement));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAchievement(
        AchievementTrigger $trigger,
        int $triggerValue,
        ?string $easterEggSlug = null,
    ): Achievement {
        return (new Achievement())
            ->setSlug('test-' . $trigger->value)
            ->setName('Test')
            ->setDescription('Desc')
            ->setTrigger($trigger)
            ->setTriggerValue($triggerValue)
            ->setXpReward(10)
            ->setRarity(AchievementRarity::COMMON)
            ->setEasterEggSlug($easterEggSlug);
    }

    /**
     * Configures the repo mock to return $achievements only for $trigger,
     * and an empty array for all other triggers.
     *
     * @param Achievement[] $achievements
     */
    private function stubRepo(AchievementTrigger $trigger, array $achievements): void
    {
        $this->repo->method('findByTrigger')
            ->willReturnCallback(fn (AchievementTrigger $t) => $t === $trigger ? $achievements : []);
    }

    private function setField(string $field, mixed $value): void
    {
        $ref = new \ReflectionProperty(UserProgression::class, $field);
        $ref->setValue($this->progression, $value);
    }
}
