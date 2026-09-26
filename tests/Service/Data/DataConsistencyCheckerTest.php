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

    public function testConcentrationSumNormalNoViolation(): void
    {
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
        $v = $this->checker->checkConcentrationSums([
            1 => 300_000.0,
        ]);
        self::assertCount(1, $v);
        self::assertSame('warning', $v[0]['severity']);
    }

    public function testConcentrationSumBoundaryAt20PercentNoViolation(): void
    {
        self::assertSame([], $this->checker->checkConcentrationSums([
            1 => 200_000.0,
        ]));
    }

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

    public function testValidCookingMomentsNoViolation(): void
    {
        self::assertSame([], $this->checker->checkCookingMoments([
            [
                'id' => 1,
                'step' => 0,
            ],
            [
                'id' => 2,
                'step' => 4,
            ],
        ]));
    }

    public function testUnknownCookingMomentIsError(): void
    {
        $v = $this->checker->checkCookingMoments([[
            'id' => 7,
            'step' => 5,
        ]]);

        self::assertCount(1, $v);
        self::assertSame('error', $v[0]['severity']);
        self::assertStringContainsString('#7', $v[0]['message']);
    }

    public function testDuoWithSameSpiceNoViolation(): void
    {
        self::assertSame([], $this->checker->checkSpiceDuoSpices([
            [
                'id' => 1,
                'prep_spice_id' => 3,
                'cook_spice_id' => 3,
            ],
        ]));
    }

    public function testDuoAcrossSpicesIsError(): void
    {
        $v = $this->checker->checkSpiceDuoSpices([
            [
                'id' => 9,
                'prep_spice_id' => 3,
                'cook_spice_id' => 4,
            ],
        ]);

        self::assertCount(1, $v);
        self::assertSame('error', $v[0]['severity']);
        self::assertStringContainsString('#9', $v[0]['message']);
    }

    public function testNoDuplicateTipsNoViolation(): void
    {
        self::assertSame([], $this->checker->checkDuplicatePreparationTips([]));
        self::assertSame([], $this->checker->checkDuplicateCookingMoments([]));
    }

    public function testDuplicatePreparationTipsIsError(): void
    {
        $v = $this->checker->checkDuplicatePreparationTips([
            [
                'spice_id' => 3,
                'preparation_method_id' => 8,
                'total' => 2,
            ],
        ]);

        self::assertCount(1, $v);
        self::assertSame('error', $v[0]['severity']);
        self::assertStringContainsString('méthode 8', $v[0]['message']);
    }

    public function testDuplicateCookingMomentsIsError(): void
    {
        $v = $this->checker->checkDuplicateCookingMoments([
            [
                'spice_id' => 3,
                'step' => 2,
                'total' => 2,
            ],
        ]);

        self::assertCount(1, $v);
        self::assertSame('error', $v[0]['severity']);
        self::assertStringContainsString('moment 2', $v[0]['message']);
    }
}
