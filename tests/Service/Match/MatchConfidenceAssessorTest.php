<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\Service\Match\MatchConfidenceAssessor;
use App\ValueObject\Match\MortarIds;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class MatchConfidenceAssessorTest extends TestCase
{
    /**
     * @param list<string> $concentrationTiers
     * @param list<string> $odtTiers
     */
    private function makeAssessor(array $concentrationTiers, array $odtTiers): MatchConfidenceAssessor
    {
        $conn = $this->createMock(Connection::class);
        // 1er appel = concentrations, 2e = ODT
        $conn->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls($concentrationTiers, $odtTiers);

        return new MatchConfidenceAssessor($conn);
    }

    public function testNoDataReturnsPlaceholder(): void
    {
        $assessor = $this->makeAssessor([], []);
        self::assertSame(DataConfidence::PLACEHOLDER, $assessor->assess(new MortarIds([1]), OdtMatrix::AIR));
    }

    public function testAllMeasuredReturnsMeasured(): void
    {
        $assessor = $this->makeAssessor(['measured'], ['measured']);
        self::assertSame(DataConfidence::MEASURED, $assessor->assess(new MortarIds([1]), OdtMatrix::AIR));
    }

    public function testWeakestLinkWins(): void
    {
        // concentrations mesurées mais ODT provisoire → global provisoire
        $assessor = $this->makeAssessor(['measured', 'literature'], ['placeholder']);
        self::assertSame(DataConfidence::PLACEHOLDER, $assessor->assess(new MortarIds([1, 2]), OdtMatrix::AIR));
    }

    public function testWeakestAcrossConcentrations(): void
    {
        $assessor = $this->makeAssessor(['measured', 'estimated'], ['literature']);
        self::assertSame(DataConfidence::ESTIMATED, $assessor->assess(new MortarIds([1]), OdtMatrix::AIR));
    }

    public function testInvalidTierStringsAreIgnored(): void
    {
        // une valeur corrompue en base ne doit pas casser l'agrégat
        $assessor = $this->makeAssessor(['literature', 'garbage'], ['literature']);
        self::assertSame(DataConfidence::LITERATURE, $assessor->assess(new MortarIds([1]), OdtMatrix::AIR));
    }

    public function testMatrixIsPassedThrough(): void
    {
        // Sanity : l'appel fonctionne avec une matrice non-air
        $assessor = $this->makeAssessor(['literature'], ['estimated']);
        self::assertSame(DataConfidence::ESTIMATED, $assessor->assess(new MortarIds([1]), OdtMatrix::WATER));
    }
}
