<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\AchievementRarity;
use PHPUnit\Framework\TestCase;

final class AchievementRarityTest extends TestCase
{
    public function testCommonLabel(): void
    {
        self::assertSame('Graine', AchievementRarity::COMMON->label());
    }

    public function testRareLabel(): void
    {
        self::assertSame('Infusion', AchievementRarity::RARE->label());
    }

    public function testEpicLabel(): void
    {
        self::assertSame('Extraction', AchievementRarity::EPIC->label());
    }

    public function testLegendaryLabel(): void
    {
        self::assertSame('Essence', AchievementRarity::LEGENDARY->label());
    }

    public function testDbValuesUnchanged(): void
    {
        self::assertSame('common', AchievementRarity::COMMON->value);
        self::assertSame('rare', AchievementRarity::RARE->value);
        self::assertSame('epic', AchievementRarity::EPIC->value);
        self::assertSame('legendary', AchievementRarity::LEGENDARY->value);
    }
}
