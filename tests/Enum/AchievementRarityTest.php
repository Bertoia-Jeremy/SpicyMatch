<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\AchievementRarity;
use PHPUnit\Framework\TestCase;

final class AchievementRarityTest extends TestCase
{
    public function testCommonLabel(): void
    {
        self::assertSame('enum.rarity.common', AchievementRarity::COMMON->label());
    }

    public function testRareLabel(): void
    {
        self::assertSame('enum.rarity.rare', AchievementRarity::RARE->label());
    }

    public function testEpicLabel(): void
    {
        self::assertSame('enum.rarity.epic', AchievementRarity::EPIC->label());
    }

    public function testLegendaryLabel(): void
    {
        self::assertSame('enum.rarity.legendary', AchievementRarity::LEGENDARY->label());
    }

    public function testDbValuesUnchanged(): void
    {
        self::assertSame('common', AchievementRarity::COMMON->value);
        self::assertSame('rare', AchievementRarity::RARE->value);
        self::assertSame('epic', AchievementRarity::EPIC->value);
        self::assertSame('legendary', AchievementRarity::LEGENDARY->value);
    }
}
