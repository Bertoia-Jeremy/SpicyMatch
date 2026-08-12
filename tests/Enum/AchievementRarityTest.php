<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\AchievementRarity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AchievementRarityTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function testLabelIsTranslationKey(AchievementRarity $rarity, string $expected): void
    {
        self::assertSame($expected, $rarity->label());
    }

    /**
     * @return iterable<string, array{AchievementRarity, string}>
     */
    public static function labelProvider(): iterable
    {
        yield 'common' => [AchievementRarity::COMMON, 'enum.rarity.common'];
        yield 'rare' => [AchievementRarity::RARE, 'enum.rarity.rare'];
        yield 'epic' => [AchievementRarity::EPIC, 'enum.rarity.epic'];
        yield 'legendary' => [AchievementRarity::LEGENDARY, 'enum.rarity.legendary'];
    }

    public function testDbValuesUnchanged(): void
    {
        self::assertSame('common', AchievementRarity::COMMON->value);
        self::assertSame('rare', AchievementRarity::RARE->value);
        self::assertSame('epic', AchievementRarity::EPIC->value);
        self::assertSame('legendary', AchievementRarity::LEGENDARY->value);
    }
}
