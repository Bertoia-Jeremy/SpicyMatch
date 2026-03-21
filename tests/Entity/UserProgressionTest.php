<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use PHPUnit\Framework\TestCase;

final class UserProgressionTest extends TestCase
{
    private UserProgression $progression;

    protected function setUp(): void
    {
        $this->progression = new UserProgression();
    }

    // ── Level formula ─────────────────────────────────────────────────────────

    public function testLevelOneAtZeroXp(): void
    {
        self::assertSame(1, $this->progression->level);
    }

    public function testLevelTwoAt247Xp(): void
    {
        // 100 * 2^1.3 = 246.22 => needs 247 for level 2
        $this->progression->addXp(247);
        self::assertSame(2, $this->progression->level);
    }

    public function testLevelThreeAt418Xp(): void
    {
        // 100 * 3^1.3 = 417.11 => needs 418 for level 3
        $this->progression->addXp(418);
        self::assertSame(3, $this->progression->level);
    }

    public function testLevel50At16200Xp(): void
    {
        // floor((16200 / 100) ^ (1/1.3)) = floor(162^0.769) = 50
        $this->progression->addXp(16200);
        self::assertSame(50, $this->progression->level);
    }

    public function testLevelScalesWithoutCap(): void
    {
        // floor((99999 / 100) ^ (1/1.3)) = 203 — no cap
        $this->progression->addXp(99999);
        self::assertSame(203, $this->progression->level);
    }

    // ── XP to next level ─────────────────────────────────────────────────────

    public function testXpToNextLevelAtZeroXp(): void
    {
        // level 1 → 2 : 100 * 2^1.3 = 246.22 => 247 XP required
        self::assertSame(247, $this->progression->xpToNextLevel);
    }

    public function testXpToNextLevelAt418Xp(): void
    {
        $this->progression->addXp(418); // level 3
        // level 3 → 4 : 100 * 4^1.3 = 606.28 => 607 XP required
        // 607 - 418 = 189
        self::assertSame(189, $this->progression->xpToNextLevel);
    }

    public function testXpToNextLevelIsAlwaysPositive(): void
    {
        // level 58 at 20000 XP → next level 59 requires 20050 XP → 50 remaining
        $this->progression->addXp(20000);
        self::assertSame(50, $this->progression->xpToNextLevel);
    }

    // ── addXp ─────────────────────────────────────────────────────────────────

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

    // ── progressPercent ──────────────────────────────────────────────────────

    public function testProgressPercentAtZeroXp(): void
    {
        // Level 1, xpForCurrent=0, xpForNext=247
        // (0 - 0) / 247 * 100 = 0
        self::assertSame(0.0, $this->progression->progressPercent);
    }

    public function testProgressPercentMidLevel(): void
    {
        $this->progression->addXp(100);
        // Level 1, range = 247-0 = 247
        // 100/247*100 = 40.5
        self::assertSame(40.5, $this->progression->progressPercent);
    }

    public function testProgressPercentAtExactLevelBoundary(): void
    {
        $this->progression->addXp(247);
        // Level 2, xpForCurrent=247, xpForNext=418
        // (247-247)/(418-247)*100 = 0.0
        self::assertSame(0.0, $this->progression->progressPercent);
    }

    public function testProgressPercentNeverExceedsHundred(): void
    {
        // Even with very high XP, progressPercent should stay in 0-100 range
        $this->progression->addXp(99999);
        $percent = $this->progression->progressPercent;
        self::assertGreaterThanOrEqual(0.0, $percent);
        self::assertLessThanOrEqual(100.0, $percent);
    }

    // ── updatedAt tracking ─────────────────────────────────────────────────

    public function testAddXpUpdatesTimestamp(): void
    {
        $before = $this->progression->getUpdatedAt();
        usleep(1000); // 1ms
        $this->progression->addXp(10);
        self::assertGreaterThanOrEqual($before, $this->progression->getUpdatedAt());
    }

    public function testSetTotalMatchesUpdatesTimestamp(): void
    {
        $before = $this->progression->getUpdatedAt();
        usleep(1000);
        $this->progression->setTotalMatches(5);
        self::assertGreaterThanOrEqual($before, $this->progression->getUpdatedAt());
    }

    public function testDisableGamificationUpdatesTimestamp(): void
    {
        $before = $this->progression->getUpdatedAt();
        usleep(1000);
        $this->progression->disableGamification();
        self::assertGreaterThanOrEqual($before, $this->progression->getUpdatedAt());
    }

    // ── setters / incrementers ─────────────────────────────────────────────

    public function testSetUniqueSpicesUsed(): void
    {
        $this->progression->setUniqueSpicesUsed(42);
        self::assertSame(42, $this->progression->getUniqueSpicesUsed());
    }

    public function testSetDiscoveries(): void
    {
        $this->progression->setDiscoveries(15);
        self::assertSame(15, $this->progression->getDiscoveries());
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

    // ── incrementSpicesRead ───────────────────────────────────────────────────

    public function testIncrementSpicesRead(): void
    {
        self::assertSame(0, $this->progression->getTotalSpicesRead());
        $this->progression->incrementSpicesRead()
            ->incrementSpicesRead();
        self::assertSame(2, $this->progression->getTotalSpicesRead());
    }

    // ── recordReadingStreak ───────────────────────────────────────────────────

    public function testFirstReadSetsStreakToOne(): void
    {
        $this->progression->recordReadingStreak();
        self::assertSame(1, $this->progression->getCurrentReadingStreak());
        self::assertSame(1, $this->progression->getLongestReadingStreak());
    }

    public function testReadSameDayDoesNotChangeStreak(): void
    {
        $this->progression->recordReadingStreak(); // lastReadDate = today, streak = 1
        $this->progression->recordReadingStreak(); // diff=0 → no change
        self::assertSame(1, $this->progression->getCurrentReadingStreak());
    }

    public function testReadYesterdayIncrementsStreak(): void
    {
        $refDate = new \ReflectionProperty(UserProgression::class, 'lastReadDate');
        $refStreak = new \ReflectionProperty(UserProgression::class, 'currentReadingStreak');
        $refLongest = new \ReflectionProperty(UserProgression::class, 'longestReadingStreak');

        $refDate->setValue($this->progression, new \DateTimeImmutable('yesterday'));
        $refStreak->setValue($this->progression, 4);
        $refLongest->setValue($this->progression, 4);

        $this->progression->recordReadingStreak();

        self::assertSame(5, $this->progression->getCurrentReadingStreak());
        self::assertSame(5, $this->progression->getLongestReadingStreak());
    }

    public function testReadAfterGapResetsStreakToOne(): void
    {
        $refDate = new \ReflectionProperty(UserProgression::class, 'lastReadDate');
        $refStreak = new \ReflectionProperty(UserProgression::class, 'currentReadingStreak');

        $refDate->setValue($this->progression, new \DateTimeImmutable('-3 days'));
        $refStreak->setValue($this->progression, 10);

        $this->progression->recordReadingStreak();

        self::assertSame(1, $this->progression->getCurrentReadingStreak());
    }

    public function testLongestStreakIsPreservedAfterReset(): void
    {
        $refDate = new \ReflectionProperty(UserProgression::class, 'lastReadDate');
        $refStreak = new \ReflectionProperty(UserProgression::class, 'currentReadingStreak');
        $refLongest = new \ReflectionProperty(UserProgression::class, 'longestReadingStreak');

        $refDate->setValue($this->progression, new \DateTimeImmutable('-5 days'));
        $refStreak->setValue($this->progression, 3);
        $refLongest->setValue($this->progression, 7);

        $this->progression->recordReadingStreak();

        self::assertSame(1, $this->progression->getCurrentReadingStreak());
        self::assertSame(7, $this->progression->getLongestReadingStreak()); // unchanged
    }

    public function testLongestStreakUpdatesWhenCurrentExceedsIt(): void
    {
        $refDate = new \ReflectionProperty(UserProgression::class, 'lastReadDate');
        $refStreak = new \ReflectionProperty(UserProgression::class, 'currentReadingStreak');
        $refLongest = new \ReflectionProperty(UserProgression::class, 'longestReadingStreak');

        $refDate->setValue($this->progression, new \DateTimeImmutable('yesterday'));
        $refStreak->setValue($this->progression, 9);
        $refLongest->setValue($this->progression, 9);

        $this->progression->recordReadingStreak(); // current = 10, longest = 10

        self::assertSame(10, $this->progression->getLongestReadingStreak());
    }

    // ── Gamification opt-out ─────────────────────────────────────────────────

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

    // ── equipBadge ────────────────────────────────────────────────────────────

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

    // ── hasAchievement / unlockAchievement ────────────────────────────────────

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

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAchievement(): Achievement
    {
        return (new Achievement())
            ->setSlug('test')
            ->setName('Test')
            ->setDescription('Test description')
            ->setTrigger(AchievementTrigger::FIRST_MATCH)
            ->setXpReward(10)
            ->setRarity(AchievementRarity::COMMON);
    }
}
