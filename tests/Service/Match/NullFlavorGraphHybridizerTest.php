<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Enum\OdtMatrix;
use App\Service\Match\NullFlavorGraphHybridizer;
use App\ValueObject\Match\MortarIds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullFlavorGraphHybridizer::class)]
final class NullFlavorGraphHybridizerTest extends TestCase
{
    public function testRerankReturnsResultsUnchanged(): void
    {
        $results = [
            [
                'id' => 10,
                'score' => 80,
                'oav_mode' => true,
            ],
            [
                'id' => 11,
                'score' => 0,
                'oav_mode' => false,
            ],
        ];

        $out = (new NullFlavorGraphHybridizer())->rerank($results, new MortarIds([1]), true, OdtMatrix::AIR);

        self::assertSame($results, $out);
    }

    public function testIsActiveReturnsFalse(): void
    {
        self::assertFalse((new NullFlavorGraphHybridizer())->isActive());
    }
}
