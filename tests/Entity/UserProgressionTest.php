<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Achievement;
use App\Entity\UserAchievement;
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
        self::assertSame(1, $this->progression->getLevel());
    }

    public function testLevelTwoAt10Xp(): void
    {
        // floor(sqrt(10/10)) + 1 = floor(1) + 1 = 2
        $this->progression->addXp(10);
        self::assertSame(2, $this->progression->getLevel());
    }

    public function testLevelThreeAt40Xp(): void
    {
        // floor(sqrt(40/10)) + 1 = floor(2) + 1 = 3
        $this->progression->addXp(40);
        self::assertSame(3, $this->progression->getLevel());
    }

    public function testLevel50At24010Xp(): void
    {
        // floor(sqrt(2401)) + 1 = 49 + 1 = 50
        $this->progression->addXp(24010);
        self::assertSame(50, $this->progression->getLevel());
    }

    public function testLevelCappedAt50WhenXpExceedsThreshold(): void
    {
        $this->progression->addXp(99999);
        self::assertSame(50, $this->progression->getLevel());
    }

    // ── XP to next level ─────────────────────────────────────────────────────

    public function testXpToNextLevelAtZeroXp(): void
    {
        // level 1 → 2 : 2² × 10 = 40 XP required
        self::assertSame(40, $this->progression->getXpToNextLevel());
    }

    public function testXpToNextLevelAt40Xp(): void
    {
        $this->progression->addXp(40); // level 3
        // level 3 → 4 : 4² × 10 - 40 = 160 - 40 = 120
        self::assertSame(120, $this->progression->getXpToNextLevel());
    }

    public function testXpToNextLevelIsZeroAtMaxLevel(): void
    {
        $this->progression->addXp(24010); // level 50
        self::assertSame(0, $this->progression->getXpToNextLevel());
    }

    // ── addXp ─────────────────────────────────────────────────────────────────

    public function testAddXpAccumulates(): void
    {
        $this->progression->addXp(10)->addXp(5);
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

    // ── incrementSpicesRead ───────────────────────────────────────────────────

    public function testIncrementSpicesRead(): void
    {
        self::assertSame(0, $this->progression->getTotalSpicesRead());
        $this->progression->incrementSpicesRead()->incrementSpicesRead();
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
