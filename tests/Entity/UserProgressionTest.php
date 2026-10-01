<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserProgressionTest extends TestCase
{
    private UserProgression $progression;

    protected function setUp(): void
    {
        $this->progression = new UserProgression();
    }

    #[DataProvider('levelFormulaProvider')]
    public function testLevelFromXp(int $xp, int $expectedLevel): void
    {
        $this->progression->addXp($xp);
        self::assertSame($expectedLevel, $this->progression->level);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function levelFormulaProvider(): iterable
    {
        yield 'level 1 at zero xp' => [0, 1];
        yield 'level 2 at 247 xp' => [247, 2];
        yield 'level 3 at 418 xp' => [418, 3];
        yield 'level 50 at 16200 xp' => [16200, 50];
        yield 'level 203 at 99999 xp (no cap)' => [99999, 203];
    }

    public function testXpToNextLevelAtZeroXp(): void
    {
        self::assertSame(247, $this->progression->xpToNextLevel);
    }

    public function testXpToNextLevelAt418Xp(): void
    {
        $this->progression->addXp(418);
        self::assertSame(189, $this->progression->xpToNextLevel);
    }

    public function testXpToNextLevelIsAlwaysPositive(): void
    {
        $this->progression->addXp(20000);
        self::assertSame(50, $this->progression->xpToNextLevel);
    }

    public function testAddXpAccumulates(): void
    {
        $this->progression->addXp(10)
            ->addXp(5);
        self::assertSame(15, $this->progression->getXp());
    }

    public function testAddXpIgnoresNegativeAmount(): void
    {
        $this->progression->addXp(-50);
        self::assertSame(0, $this->progression->getXp());
    }

    public function testAddXpIgnoresZero(): void
    {
        $this->progression->addXp(0);
        self::assertSame(0, $this->progression->getXp());
    }

    public function testProgressPercentAtZeroXp(): void
    {
        self::assertSame(0.0, $this->progression->progressPercent);
    }

    public function testProgressPercentMidLevel(): void
    {
        $this->progression->addXp(100);
        self::assertSame(40.5, $this->progression->progressPercent);
    }

    public function testProgressPercentAtExactLevelBoundary(): void
    {
        $this->progression->addXp(247);
        self::assertSame(0.0, $this->progression->progressPercent);
    }

    public function testProgressPercentNeverExceedsHundred(): void
    {
        $this->progression->addXp(99999);
        $percent = $this->progression->progressPercent;
        self::assertGreaterThanOrEqual(0.0, $percent);
        self::assertLessThanOrEqual(100.0, $percent);
    }

    public function testIncrementDiscoveries(): void
    {
        $this->progression->incrementDiscoveries();
        $this->progression->incrementDiscoveries();
        self::assertSame(2, $this->progression->getDiscoveries());
    }

    public function testIncrementMatches(): void
    {
        $this->progression->incrementMatches();
        self::assertSame(1, $this->progression->getTotalMatches());
    }

    public function testIncrementSpicesRead(): void
    {
        self::assertSame(0, $this->progression->getTotalSpicesRead());
        $this->progression->incrementSpicesRead()
            ->incrementSpicesRead();
        self::assertSame(2, $this->progression->getTotalSpicesRead());
    }

    /**
     * @return iterable<string, array{0: ?string, 1: int, 2: int, 3: int, 4: int}>
     */
    public static function activityStreakProvider(): iterable
    {
        yield 'first activity' => [null, 0, 0, 1, 1];
        yield 'same day keeps streak' => ['2026-09-30', 3, 5, 3, 5];
        yield 'yesterday increments' => ['2026-09-29', 4, 4, 5, 5];
        yield 'gap resets' => ['2026-09-27', 10, 10, 1, 10];
        yield 'longest preserved after reset' => ['2026-09-25', 3, 7, 1, 7];
        yield 'longest follows current' => ['2026-09-29', 9, 9, 10, 10];
    }

    #[DataProvider('activityStreakProvider')]
    public function testRecordActivityStreak(
        ?string $lastActivity,
        int $current,
        int $longest,
        int $expectedCurrent,
        int $expectedLongest,
    ): void {
        new \ReflectionProperty(UserProgression::class, 'lastActivityDate')->setValue(
            $this->progression,
            $lastActivity === null ? null : new \DateTimeImmutable($lastActivity),
        );
        new \ReflectionProperty(UserProgression::class, 'currentActivityStreak')->setValue($this->progression, $current);
        new \ReflectionProperty(UserProgression::class, 'longestActivityStreak')->setValue($this->progression, $longest);

        $this->progression->recordActivityStreak(new \DateTimeImmutable('2026-09-30 15:42'));

        self::assertSame($expectedCurrent, $this->progression->getCurrentActivityStreak());
        self::assertSame($expectedLongest, $this->progression->getLongestActivityStreak());
    }

    public function testGamificationEnabledByDefault(): void
    {
        self::assertTrue($this->progression->isGamificationEnabled());
    }

    public function testDisableGamification(): void
    {
        $this->progression->disableGamification();
        self::assertFalse($this->progression->isGamificationEnabled());
    }

    public function testEnableGamificationAfterDisable(): void
    {
        $this->progression->disableGamification();
        $this->progression->enableGamification();
        self::assertTrue($this->progression->isGamificationEnabled());
    }

    public function testDisableGamificationClearsEquippedBadge(): void
    {
        $achievement = $this->makeAchievement();
        $ua = $this->progression->unlockAchievement($achievement);
        $this->progression->equipBadge($ua);

        self::assertNotNull($this->progression->getEquippedBadge());

        $this->progression->disableGamification();

        self::assertNull($this->progression->getEquippedBadge());
    }

    public function testEquipBadgeSetsEquippedBadge(): void
    {
        $ua = $this->progression->unlockAchievement($this->makeAchievement());
        $this->progression->equipBadge($ua);

        self::assertSame($ua, $this->progression->getEquippedBadge());
    }

    public function testEquipNullUnequipsBadge(): void
    {
        $ua = $this->progression->unlockAchievement($this->makeAchievement());
        $this->progression->equipBadge($ua);
        $this->progression->equipBadge(null);

        self::assertNull($this->progression->getEquippedBadge());
    }

    public function testEquipBadgeThrowsWhenBadgeBelongsToOtherProgression(): void
    {
        $other = new UserProgression();
        $ua = $other->unlockAchievement($this->makeAchievement());

        $this->expectException(\InvalidArgumentException::class);
        $this->progression->equipBadge($ua);
    }

    public function testHasAchievementReturnsFalseByDefault(): void
    {
        self::assertFalse($this->progression->hasAchievement($this->makeAchievement()));
    }

    public function testHasAchievementReturnsTrueAfterUnlock(): void
    {
        $achievement = $this->makeAchievement();
        $this->progression->unlockAchievement($achievement);

        self::assertTrue($this->progression->hasAchievement($achievement));
    }

    public function testUnlockAchievementAddsToCollection(): void
    {
        $this->progression->unlockAchievement($this->makeAchievement());

        self::assertCount(1, $this->progression->getUserAchievements());
    }

    private function makeAchievement(): Achievement
    {
        return new Achievement()
            ->setSlug('test')
            ->setName('Test')
            ->setDescription('Test description')
            ->setTrigger(AchievementTrigger::FIRST_MATCH)
            ->setXpReward(10)
            ->setRarity(AchievementRarity::COMMON);
    }
}
