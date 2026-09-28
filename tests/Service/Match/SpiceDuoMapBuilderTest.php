<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Service\Match\SpiceDuoMapBuilder;
use PHPUnit\Framework\TestCase;

final class SpiceDuoMapBuilderTest extends TestCase
{
    public function testEmptyRowsGiveEmptyMaps(): void
    {
        $builder = new SpiceDuoMapBuilder();

        self::assertSame([
            'byPrep' => [],
            'byCook' => [],
        ], $builder->build([]));
        self::assertSame([
            'byPrep' => [],
            'byCook' => [],
        ], $builder->tooltips([]));
    }

    public function testBuildIndexesBothDirectionsAndKeepsMultiplicity(): void
    {
        $map = new SpiceDuoMapBuilder()
            ->build([
                [
                    'prepId' => 10,
                    'cookId' => 1,
                    'rank' => 1,
                ],
                [
                    'prepId' => 10,
                    'cookId' => 2,
                    'rank' => 2,
                ],
                [
                    'prepId' => 11,
                    'cookId' => 1,
                    'rank' => 2,
                ],
            ]);

        self::assertSame([
            [
                'c' => 1,
                'r' => 1,
            ],
            [
                'c' => 2,
                'r' => 2,
            ],
        ], $map['byPrep'][10]);
        self::assertSame([
            [
                'p' => 10,
                'r' => 1,
            ],
            [
                'p' => 11,
                'r' => 2,
            ],
        ], $map['byCook'][1]);
        self::assertSame([
            [
                'p' => 10,
                'r' => 2,
            ],
        ], $map['byCook'][2]);
    }

    public function testTooltipsCarryTextForBothSides(): void
    {
        $tips = new SpiceDuoMapBuilder()
            ->tooltips([
                [
                    'prepId' => 10,
                    'cookId' => 1,
                    'prepTitle' => 'Infusion',
                    'title' => 'Fond doré',
                    'effect' => 'effet',
                ],
            ]);

        $expected = [
            'p' => 10,
            'c' => 1,
            'method' => 'Infusion',
            'title' => 'Fond doré',
            'effect' => 'effet',
        ];
        self::assertSame([$expected], $tips['byPrep'][10]);
        self::assertSame([$expected], $tips['byCook'][1]);
    }
}
