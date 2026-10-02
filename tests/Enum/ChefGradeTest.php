<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\ChefGrade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChefGradeTest extends TestCase
{
    #[DataProvider('levelProvider')]
    public function testFromLevelHonoursThresholds(int $level, ChefGrade $expected): void
    {
        self::assertSame($expected, ChefGrade::fromLevel($level));
    }

    /**
     * @return iterable<string, array{int, ChefGrade}>
     */
    public static function levelProvider(): iterable
    {
        yield 'first level' => [1, ChefGrade::COMMIS];
        yield 'last commis' => [19, ChefGrade::COMMIS];
        yield 'first chef de partie' => [20, ChefGrade::CHEF_PARTIE];
        yield 'last chef de partie' => [49, ChefGrade::CHEF_PARTIE];
        yield 'first saucier' => [50, ChefGrade::SAUCIER];
        yield 'last saucier' => [79, ChefGrade::SAUCIER];
        yield 'first chef executif' => [80, ChefGrade::CHEF_EXECUTIF];
    }

    public function testLabelsPointToExistingGradeKeys(): void
    {
        self::assertSame(
            ['ui.grade.commis', 'ui.grade.chef_partie', 'ui.grade.saucier', 'ui.grade.chef_executif'],
            array_map(static fn (ChefGrade $grade): string => $grade->label(), ChefGrade::cases()),
        );
    }
}
