<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\Repository\FlavorGraphAffinityRepository;
use App\Service\Match\FlavorGraphHybridizer;
use App\Service\Match\MatchConfidenceAssessorInterface;
use App\ValueObject\Match\MortarIds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlavorGraphHybridizer::class)]
final class FlavorGraphHybridizerTest extends TestCase
{
    /**
     * @param array<int, array<int, float>> $fgProfiles
     */
    private function makeHybridizer(array $fgProfiles, DataConfidence $tier): FlavorGraphHybridizer
    {
        $repo = $this->createStub(FlavorGraphAffinityRepository::class);
        $repo->method('loadPairwiseBatch')
            ->willReturn($fgProfiles);

        $assessor = $this->createStub(MatchConfidenceAssessorInterface::class);
        $assessor->method('assess')
            ->willReturn($tier);

        return new FlavorGraphHybridizer($repo, $assessor);
    }

    public function testEmptyResultsReturnedUntouched(): void
    {
        $hybridizer = $this->makeHybridizer([], DataConfidence::PLACEHOLDER);

        self::assertSame([], $hybridizer->rerank([], new MortarIds([1]), true, OdtMatrix::AIR));
    }

    public function testDegradedModeScoresPurelyFromFlavorGraph(): void
    {
        $hybridizer = $this->makeHybridizer([
            10 => [
                1 => 0.60,
            ],
        ], DataConfidence::PLACEHOLDER);
        $results = [[
            'id' => 10,
            'score' => 0,
            'oav_mode' => false,
        ]];

        $out = $hybridizer->rerank($results, new MortarIds([1]), false, OdtMatrix::AIR);

        self::assertSame(60, $out[0]['score']);
    }

    public function testPlaceholderTierWeightsFlavorGraphHeavily(): void
    {
        $hybridizer = $this->makeHybridizer([
            10 => [
                1 => 0.40,
            ],
        ], DataConfidence::PLACEHOLDER);
        $results = [[
            'id' => 10,
            'score' => 80,
            'oav_mode' => true,
        ]];

        $out = $hybridizer->rerank($results, new MortarIds([1]), true, OdtMatrix::AIR);

        self::assertSame(46, $out[0]['score']);
    }

    public function testMeasuredTierWeightsOavHeavily(): void
    {
        $hybridizer = $this->makeHybridizer([
            10 => [
                1 => 0.40,
            ],
        ], DataConfidence::MEASURED);
        $results = [[
            'id' => 10,
            'score' => 80,
            'oav_mode' => true,
        ]];

        $out = $hybridizer->rerank($results, new MortarIds([1]), true, OdtMatrix::AIR);

        self::assertSame(74, $out[0]['score']);
    }

    public function testCandidateAbsentFromFlavorGraphKeepsOavScore(): void
    {
        $hybridizer = $this->makeHybridizer([], DataConfidence::PLACEHOLDER);
        $results = [[
            'id' => 10,
            'score' => 80,
            'oav_mode' => true,
        ]];

        $out = $hybridizer->rerank($results, new MortarIds([1]), true, OdtMatrix::AIR);

        self::assertSame(80, $out[0]['score']);
    }

    public function testMissingMortarLinkCountsAsZeroInMean(): void
    {
        $hybridizer = $this->makeHybridizer([
            10 => [
                1 => 0.80,
            ],
        ], DataConfidence::PLACEHOLDER);
        $results = [[
            'id' => 10,
            'score' => 0,
            'oav_mode' => false,
        ]];

        $out = $hybridizer->rerank($results, new MortarIds([1, 2]), false, OdtMatrix::AIR);

        self::assertSame(40, $out[0]['score']);
    }

    public function testIsActiveReturnsTrue(): void
    {
        self::assertTrue($this->makeHybridizer([], DataConfidence::MEASURED)->isActive());
    }

    public function testExplicitTierSkipsAssessor(): void
    {
        $repo = $this->createStub(FlavorGraphAffinityRepository::class);
        $repo->method('loadPairwiseBatch')
            ->willReturn([
                10 => [
                    1 => 0.40,
                ],
            ]);

        $assessor = $this->createMock(MatchConfidenceAssessorInterface::class);
        $assessor->expects(self::never())->method('assess');

        $hybridizer = new FlavorGraphHybridizer($repo, $assessor);
        $results = [[
            'id' => 10,
            'score' => 80,
            'oav_mode' => true,
        ]];

        $out = $hybridizer->rerank($results, new MortarIds([1]), true, OdtMatrix::AIR, DataConfidence::MEASURED);

        self::assertSame(74, $out[0]['score']);
    }
}
