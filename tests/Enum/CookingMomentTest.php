<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\CookingMoment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CookingMomentTest extends TestCase
{
    public function testBackingValuesMatchLegacyStepColumn(): void
    {
        self::assertSame([0, 1, 2, 3, 4], array_column(CookingMoment::cases(), 'value'));
    }

    #[DataProvider('outOfRangeProvider')]
    public function testTryFromRejectsOutOfRange(int $value): void
    {
        self::assertNull(CookingMoment::tryFrom($value));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfRangeProvider(): iterable
    {
        yield 'above last' => [5];
        yield 'negative' => [-1];
    }

    #[DataProvider('keyProvider')]
    public function testTranslationKeys(CookingMoment $moment, string $slug): void
    {
        self::assertSame("enum.cooking_moment.{$slug}.label", $moment->label());
        self::assertSame("enum.cooking_moment.{$slug}.hint", $moment->hint());
        self::assertSame("enum.cooking_moment.{$slug}.chef", $moment->chefView());
    }

    /**
     * @return iterable<string, array{CookingMoment, string}>
     */
    public static function keyProvider(): iterable
    {
        yield 'pre' => [CookingMoment::PRE, 'pre'];
        yield 'start' => [CookingMoment::START, 'start'];
        yield 'simmer' => [CookingMoment::SIMMER, 'simmer'];
        yield 'finish' => [CookingMoment::FINISH, 'finish'];
        yield 'plating' => [CookingMoment::PLATING, 'plating'];
    }

    public function testIconsAreDistinctFontAwesomeNames(): void
    {
        $icons = array_map(static fn (CookingMoment $m): string => $m->icon(), CookingMoment::cases());

        self::assertCount(5, array_unique($icons));
        foreach ($icons as $icon) {
            self::assertStringStartsWith('fa-', $icon);
        }
    }
}
