<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\DataConfidence;
use PHPUnit\Framework\TestCase;

final class DataConfidenceTest extends TestCase
{
    public function testRankOrdering(): void
    {
        self::assertGreaterThan(DataConfidence::LITERATURE->rank(), DataConfidence::MEASURED->rank());
        self::assertGreaterThan(DataConfidence::ESTIMATED->rank(), DataConfidence::LITERATURE->rank());
        self::assertGreaterThan(DataConfidence::PLACEHOLDER->rank(), DataConfidence::ESTIMATED->rank());
    }

    public function testTierLetters(): void
    {
        self::assertSame('A', DataConfidence::MEASURED->tier());
        self::assertSame('B', DataConfidence::LITERATURE->tier());
        self::assertSame('C', DataConfidence::ESTIMATED->tier());
        self::assertSame('D', DataConfidence::PLACEHOLDER->tier());
    }

    public function testIsProductionGrade(): void
    {
        self::assertTrue(DataConfidence::MEASURED->isProductionGrade());
        self::assertTrue(DataConfidence::LITERATURE->isProductionGrade());
        self::assertFalse(DataConfidence::ESTIMATED->isProductionGrade());
        self::assertFalse(DataConfidence::PLACEHOLDER->isProductionGrade());
    }

    public function testWeakestReturnsLowestTier(): void
    {
        self::assertSame(
            DataConfidence::PLACEHOLDER,
            DataConfidence::weakest(DataConfidence::MEASURED, DataConfidence::PLACEHOLDER, DataConfidence::LITERATURE),
        );

        self::assertSame(
            DataConfidence::LITERATURE,
            DataConfidence::weakest(DataConfidence::MEASURED, DataConfidence::LITERATURE),
        );
    }

    public function testWeakestEmptyDefaultsToPlaceholder(): void
    {
        self::assertSame(DataConfidence::PLACEHOLDER, DataConfidence::weakest());
    }

    public function testLabelsAreTranslationKeys(): void
    {
        self::assertSame('enum.confidence.measured', DataConfidence::MEASURED->label());
        self::assertSame('enum.confidence.placeholder', DataConfidence::PLACEHOLDER->label());
    }
}
