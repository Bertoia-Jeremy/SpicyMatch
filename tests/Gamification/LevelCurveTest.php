<?php

declare(strict_types=1);

namespace App\Tests\Gamification;

use App\Gamification\LevelCurve;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LevelCurveTest extends TestCase
{
    #[DataProvider('xpProvider')]
    public function testLevelForFollowsCurve(int $xp, int $expected): void
    {
        self::assertSame($expected, LevelCurve::levelFor($xp));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function xpProvider(): iterable
    {
        yield 'negative xp' => [-50, 1];
        yield 'zero xp' => [0, 1];
        yield 'just below level 2' => [246, 1];
        yield 'level 2 threshold' => [247, 2];
        yield 'level 10 threshold' => [1996, 10];
        yield 'just below level 10' => [1995, 9];
    }

    #[DataProvider('thresholdProvider')]
    public function testThresholdForFollowsCurve(int $level, int $expected): void
    {
        self::assertSame($expected, LevelCurve::thresholdFor($level));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function thresholdProvider(): iterable
    {
        yield 'level 0' => [0, 0];
        yield 'level 1' => [1, 0];
        yield 'level 2' => [2, 247];
        yield 'level 10' => [10, 1996];
    }

    public function testThresholdIsTheFirstXpOfItsLevel(): void
    {
        foreach (range(2, 60) as $level) {
            $threshold = LevelCurve::thresholdFor($level);

            self::assertSame($level, LevelCurve::levelFor($threshold), "level {$level}");
            self::assertSame($level - 1, LevelCurve::levelFor($threshold - 1), "below level {$level}");
        }
    }
}
