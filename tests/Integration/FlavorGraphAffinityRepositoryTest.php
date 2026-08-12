<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repository\FlavorGraphAffinityRepository;
use App\Tests\Support\IntegrationTestCase;
use App\ValueObject\Match\MortarIds;
use Doctrine\DBAL\Connection;

final class FlavorGraphAffinityRepositoryTest extends IntegrationTestCase
{
    private FlavorGraphAffinityRepository $repo;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = static::getContainer()->get(FlavorGraphAffinityRepository::class);
        $this->connection = $this->em->getConnection();
    }

    public function testEmptyCandidatesReturnEmptyMap(): void
    {
        self::assertSame([], $this->repo->loadPairwiseBatch([], new MortarIds([1])));
    }

    public function testLoadPairwiseBatchBuildsNestedCandidateToMortarMap(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->insertPairing(901, 801, 0.9);
            $this->insertPairing(901, 802, 0.5);
            $this->insertPairing(902, 801, 0.3);
            $this->insertPairing(903, 999, 0.7);
            $this->insertPairing(888, 801, 0.6);

            $map = $this->repo->loadPairwiseBatch([901, 902, 903], new MortarIds([801, 802]));

            self::assertArrayHasKey(901, $map);
            self::assertArrayHasKey(902, $map);
            self::assertArrayNotHasKey(903, $map, 'Candidat dont le pairing est hors mortier ne doit pas apparaître');
            self::assertArrayNotHasKey(888, $map, 'Pairing hors liste de candidats ne doit pas apparaître');

            self::assertEqualsWithDelta(0.9, $map[901][801], 1e-9);
            self::assertEqualsWithDelta(0.5, $map[901][802], 1e-9);
            self::assertEqualsWithDelta(0.3, $map[902][801], 1e-9);
            self::assertArrayNotHasKey(802, $map[902]);
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testCountTotalReflectsRowCount(): void
    {
        $this->connection->beginTransaction();

        try {
            self::assertSame(0, $this->repo->countTotal());

            $this->insertPairing(901, 801, 0.9);
            $this->insertPairing(902, 802, 0.4);

            self::assertSame(2, $this->repo->countTotal());
        } finally {
            $this->connection->rollBack();
        }
    }

    private function insertPairing(int $spiceA, int $spiceB, float $score): void
    {
        $this->connection->insert('ingredient_pairing', [
            'spice_a_id' => $spiceA,
            'spice_b_id' => $spiceB,
            'affinity_score' => $score,
        ]);
    }
}
