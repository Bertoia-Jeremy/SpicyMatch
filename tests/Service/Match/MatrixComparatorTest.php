<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Enum\OdtMatrix;
use App\Repository\SpicesRepository;
use App\Service\Match\MatchPipeline;
use App\Service\Match\MatrixComparator;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class MatrixComparatorTest extends TestCase
{
    private function makeComparator(
        ?MatchPipeline $pipeline = null,
        ?SpicesRepository $spices = null,
    ): MatrixComparator {
        return new MatrixComparator(
            $pipeline ?? $this->createStub(MatchPipeline::class),
            $spices ?? $this->createStub(SpicesRepository::class),
        );
    }

    // ── compare() ──────────────────────────────────────────────────────────────

    public function testCompareCallsPipelineForEachMatrix(): void
    {
        $pipeline = $this->createMock(MatchPipeline::class);
        $pipeline->expects(self::exactly(3))
            ->method('run')
            ->willReturn([]);

        $spices = $this->createStub(SpicesRepository::class);

        $comparator = $this->makeComparator($pipeline, $spices);
        $result = $comparator->compare(new MortarIds([1]), new CulinaryContext());

        self::assertArrayHasKey('air', $result);
        self::assertArrayHasKey('water', $result);
        self::assertArrayHasKey('oil', $result);
    }

    public function testComparePreservesNonMatrixContextFields(): void
    {
        // Le contexte de base a fat=0.5, cooking=20, temp=80. Chaque matrice doit
        // recevoir le même contexte sauf la matrice.
        $captured = [];
        $pipeline = $this->createMock(MatchPipeline::class);
        $pipeline->method('run')
            ->willReturnCallback(function (MortarIds $m, int $l, CulinaryContext $ctx) use (&$captured): array {
                $captured[] = $ctx;

                return [];
            });

        $baseCtx = new CulinaryContext(
            OdtMatrix::AIR,
            fatRatio: 0.5,
            waterRatio: 0.5,
            cookingTimeMin: 20,
            temperatureCelsius: 80
        );
        $comparator = $this->makeComparator($pipeline);
        $comparator->compare(new MortarIds([1]), $baseCtx);

        self::assertCount(3, $captured);
        foreach ($captured as $ctx) {
            self::assertSame(0.5, $ctx->fatRatio);
            self::assertSame(20, $ctx->cookingTimeMin);
            self::assertSame(80, $ctx->temperatureCelsius);
        }

        $matrices = array_map(static fn (CulinaryContext $c) => $c->matrix->value, $captured);
        self::assertEqualsCanonicalizing(['air', 'water', 'oil'], $matrices);
    }

    public function testCompareEnrichesResultsWithNames(): void
    {
        $pipeline = $this->createStub(MatchPipeline::class);
        $pipeline->method('run')
            ->willReturn([
                [
                    'id' => 42,
                    'score' => 87,
                    'oav_mode' => true,
                ],
            ]);

        $spices = $this->createStub(SpicesRepository::class);
        $spices->method('findNamesById')
            ->willReturn([
                42 => 'Marjolaine',
            ]);

        $result = $this->makeComparator($pipeline, $spices)
            ->compare(new MortarIds([1]), new CulinaryContext());

        self::assertSame('Marjolaine', $result['air'][0]['name']);
        self::assertSame(87, $result['air'][0]['score']);
    }

    public function testCompareReturnsEmptyArrayPerMatrixIfNoResults(): void
    {
        $pipeline = $this->createStub(MatchPipeline::class);
        $pipeline->method('run')
            ->willReturn([]);

        $result = $this->makeComparator($pipeline)
            ->compare(new MortarIds([1]), new CulinaryContext());

        self::assertSame([], $result['air']);
        self::assertSame([], $result['water']);
        self::assertSame([], $result['oil']);
    }

    // ── buildGrid() ────────────────────────────────────────────────────────────

    public function testBuildGridMergesRankingsByEpiceId(): void
    {
        // Épice 1 apparaît dans air et water, épice 2 uniquement dans oil.
        $rankings = [
            'air' => [[
                'id' => 1,
                'name' => 'A',
                'score' => 80,
            ]],
            'water' => [[
                'id' => 1,
                'name' => 'A',
                'score' => 50,
            ]],
            'oil' => [[
                'id' => 2,
                'name' => 'B',
                'score' => 60,
            ]],
        ];

        $grid = $this->makeComparator()
            ->buildGrid($rankings);

        self::assertCount(2, $grid);

        $byId = array_column($grid, null, 'id');
        self::assertSame(80, $byId[1]['scores']['air']);
        self::assertSame(50, $byId[1]['scores']['water']);
        self::assertSame(0, $byId[1]['scores']['oil'], 'Épice absente d\'une matrice → score 0');
        self::assertSame(0, $byId[2]['scores']['air']);
        self::assertSame(60, $byId[2]['scores']['oil']);
    }

    public function testBuildGridSortsByMaxScoreDescending(): void
    {
        $rankings = [
            'air' => [
                [
                    'id' => 1,
                    'name' => 'A',
                    'score' => 30,
                ],
                [
                    'id' => 2,
                    'name' => 'B',
                    'score' => 90,
                ],
            ],
            'water' => [],
            'oil' => [],
        ];

        $grid = $this->makeComparator()
            ->buildGrid($rankings);

        // B (max=90) doit précéder A (max=30)
        self::assertSame(2, $grid[0]['id']);
        self::assertSame(1, $grid[1]['id']);
    }

    public function testBuildGridReturnsEmptyWhenAllRankingsEmpty(): void
    {
        $grid = $this->makeComparator()
            ->buildGrid([
                'air' => [],
                'water' => [],
                'oil' => [],
            ]);

        self::assertSame([], $grid);
    }
}
