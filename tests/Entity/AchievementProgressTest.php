<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Achievement;
use App\Entity\AchievementProgress;
use PHPUnit\Framework\TestCase;

final class AchievementProgressTest extends TestCase
{
    public function testIsCompletedHook(): void
    {
        $achievement = new Achievement();
        $achievement->setTriggerValue(5);

        $progress = new AchievementProgress();
        $progress->setAchievement($achievement);

        self::assertFalse($progress->isCompleted);

        $progress->setProgress(4);
        self::assertFalse($progress->isCompleted);

        $progress->setProgress(5);
        self::assertTrue($progress->isCompleted);

        $progress->setProgress(10);
        self::assertTrue($progress->isCompleted);
    }

    public function testIncrementProgressByOne(): void
    {
        $progress = new AchievementProgress();
        self::assertSame(0, $progress->getProgress());

        $progress->incrementProgress();
        self::assertSame(1, $progress->getProgress());
    }

    public function testIncrementProgressMultipleTimes(): void
    {
        $progress = new AchievementProgress();
        $progress->incrementProgress()
            ->incrementProgress()
            ->incrementProgress();
        self::assertSame(3, $progress->getProgress());
    }

    public function testSetProgressDirectly(): void
    {
        $progress = new AchievementProgress();
        $progress->setProgress(15);
        self::assertSame(15, $progress->getProgress());
    }
}
