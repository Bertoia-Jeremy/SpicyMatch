<?php

declare(strict_types=1);

namespace App\Tests\Service\Data;

use App\Service\Data\DataConsistencyChecker;
use PHPUnit\Framework\TestCase;

final class DataConsistencyCheckerTest extends TestCase
{
    private DataConsistencyChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new DataConsistencyChecker();
    }

    // ── OAV ─────────────────────────────────────────────────────────────────

    public function testOavWithinRangeNoViolation(): void
    {
        $rows = [
            [
                'spice_id' => 1,
                'aromatic_compound_id' => 10,
                'matrix' => 'air',
                'oav_value' => 5000.0,
            ],
        ];
        self::assertSame([], $this->checker->checkOavValues($rows));
    }

    public function testOavBelowOneIsError(): void
    {
        $rows = [
            [
                'spice_id' => 1,
                'aromatic_compound_id' => 10,
                'matrix' => 'air',
                'oav_value' => 0.5,
            ],
        ];
        $v = $this->checker->checkOavValues($rows);
        self::assertCount(1, $v);
        self::assertSame('error', $v[0]['severity']);
        self::assertStringContainsString('invariant cassé', $v[0]['message']);
    }

    public function testOavExactlyOneIsError(): void
    {
        // OAV = 1 = seuil, doit être strictement > 1
        $rows = [
            [
                'spice_id' => 2,
                'aromatic_compound_id' => 11,
                'matrix' => 'water',
                'oav_value' => 1.0,
            ],
        ];
        $v = $this->checker->checkOavValues($rows);
        self::assertCount(1, $v);
        self::assertSame('error', $v[0]['severity']);
    }

    public function testOavAbovePlausibleCeilingIsWarning(): void
    {
        $rows = [
            [
                'spice_id' => 1,
                'aromatic_compound_id' => 10,
                'matrix' => 'air',
                'oav_value' => 5.0e9,
            ],
        ];
        $v = $this->checker->checkOavValues($rows);
        self::assertCount(1, $v);
        self::assertSame('warning', $v[0]['severity']);
    }

    // ── Sommes de concentrations ─────────────────────────────────────────────

    public function testConcentrationSumNormalNoViolation(): void
    {
        // 50 000 ppm = 5 % — plausible
        self::assertSame([], $this->checker->checkConcentrationSums([
            1 => 50_000.0,
        ]));
    }

    public function testConcentrationSumImpossibleIsError(): void
    {
        $v = $this->checker->checkConcentrationSums([
            1 => 1_500_000.0,
        ], [
            1 => 'Clou de Girofle',
        ]);
        self::assertCount(1, $v);
        self::assertSame('error', $v[0]['severity']);
        self::assertStringContainsString('Clou de Girofle', $v[0]['message']);
    }

    public function testConcentrationSumImplausibleIsWarning(): void
    {
        // 300 000 ppm = 30 % — implausible mais pas impossible
        $v = $this->checker->checkConcentrationSums([
            1 => 300_000.0,
        ]);
        self::assertCount(1, $v);
        self::assertSame('warning', $v[0]['severity']);
    }

    public function testConcentrationSumBoundaryAt20PercentNoViolation(): void
    {
        // Exactement 200 000 = 20 %, non strictement supérieur → pas de violation
        self::assertSame([], $this->checker->checkConcentrationSums([
            1 => 200_000.0,
        ]));
    }

    // ── ODT air manquant ──────────────────────────────────────────────────────

    public function testMissingAirOdtIsWarning(): void
    {
        $v = $this->checker->checkMissingAirOdt([
            [
                'id' => 42,
                'name' => 'Carvone',
            ],
        ]);
        self::assertCount(1, $v);
        self::assertSame('warning', $v[0]['severity']);
        self::assertStringContainsString('Carvone', $v[0]['message']);
    }

    public function testNoMissingAirOdtNoViolation(): void
    {
        self::assertSame([], $this->checker->checkMissingAirOdt([]));
    }
}
