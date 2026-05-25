<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\SpicesRepository;
use App\Service\SpiceGroupFinderService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit tests for SpiceGroupFinderService.
 *
 * Verifies output format and delegation to the repository.
 * SQL query correctness is validated in integration tests.
 */
#[AllowMockObjectsWithoutExpectations]
class SpiceGroupFinderServiceTest extends TestCase
{
    private SpicesRepository&MockObject $repository;
    private SpiceGroupFinderService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SpicesRepository::class);
        // ArrayAdapter = cache mémoire vide à chaque test — évite de mocker CacheInterface.
        $this->service = new SpiceGroupFinderService($this->repository, new ArrayAdapter());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // findTopPairs
    // ──────────────────────────────────────────────────────────────────────────

    public function testFindTopPairsReturnsEmptyArrayWhenNoData(): void
    {
        $this->repository->method('findTopCompatiblePairs')
            ->willReturn([]);
        self::assertSame([], $this->service->findTopPairs());
    }

    public function testFindTopPairsForwardsLimitToRepository(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findTopCompatiblePairs')
            ->with(5)
            ->willReturn([]);

        $this->service->findTopPairs(5);
    }

    public function testFindTopPairsOutputFormat(): void
    {
        $row = [
            's1_id' => '1',
            's1_name' => 'Thym',
            's1_file' => null,
            's1_color' => '#15803d',
            's1_group' => 'Monoterpènes',
            's2_id' => '2',
            's2_name' => 'Origan',
            's2_file' => null,
            's2_color' => '#15803d',
            's2_group' => 'Monoterpènes',
            'score' => '6',
            'shared_main' => '2',
            'shared_secondary' => '0',
        ];

        $this->repository->method('findTopCompatiblePairs')
            ->willReturn([$row]);

        $result = $this->service->findTopPairs(1);

        self::assertCount(1, $result);
        $pair = $result[0];

        self::assertArrayHasKey('score', $pair);
        self::assertArrayHasKey('shared_main', $pair);
        self::assertArrayHasKey('shared_secondary', $pair);
        self::assertArrayHasKey('spices', $pair);
        self::assertCount(2, $pair['spices']);

        // Types must be cast to int
        self::assertIsInt($pair['score']);
        self::assertSame(6, $pair['score']);
        self::assertSame(2, $pair['shared_main']);
        self::assertSame(0, $pair['shared_secondary']);

        // First spice
        self::assertSame(1, $pair['spices'][0]['id']);
        self::assertSame('Thym', $pair['spices'][0]['name']);
        self::assertNull($pair['spices'][0]['file']);
        self::assertSame('#15803d', $pair['spices'][0]['color']);
        self::assertSame('Monoterpènes', $pair['spices'][0]['groupName']);

        // Second spice
        self::assertSame(2, $pair['spices'][1]['id']);
        self::assertSame('Origan', $pair['spices'][1]['name']);
    }

    public function testFindTopPairsNullColorAndGroupHandled(): void
    {
        $row = [
            's1_id' => '10',
            's1_name' => 'Épice sans groupe',
            's1_file' => null,
            's1_color' => null,
            's1_group' => null,
            's2_id' => '11',
            's2_name' => 'Épice B',
            's2_file' => 'image.jpg',
            's2_color' => null,
            's2_group' => null,
            'score' => '3',
            'shared_main' => '1',
            'shared_secondary' => '0',
        ];

        $this->repository->method('findTopCompatiblePairs')
            ->willReturn([$row]);

        $result = $this->service->findTopPairs(1);

        self::assertNull($result[0]['spices'][0]['color']);
        self::assertNull($result[0]['spices'][0]['groupName']);
        self::assertSame('image.jpg', $result[0]['spices'][1]['file']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // findTopTriplets
    // ──────────────────────────────────────────────────────────────────────────

    public function testFindTopTripletsReturnsEmptyArrayWhenNoData(): void
    {
        $this->repository->method('findTopCompatibleTriplets')
            ->willReturn([]);
        self::assertSame([], $this->service->findTopTriplets());
    }

    public function testFindTopTripletsForwardsLimitToRepository(): void
    {
        $this->repository
            ->expects(self::once())
            ->method('findTopCompatibleTriplets')
            ->with(3)
            ->willReturn([]);

        $this->service->findTopTriplets(3);
    }

    public function testFindTopTripletsOutputFormat(): void
    {
        $row = [
            's1_id' => '1',
            's1_name' => 'Thym',
            's1_file' => null,
            's1_color' => '#15803d',
            's1_group' => 'Monoterpènes',
            's2_id' => '2',
            's2_name' => 'Origan',
            's2_file' => null,
            's2_color' => '#15803d',
            's2_group' => 'Monoterpènes',
            's3_id' => '3',
            's3_name' => 'Cumin',
            's3_file' => null,
            's3_color' => '#15803d',
            's3_group' => 'Monoterpènes',
            'score' => '6',
            'shared_main' => '2',
            'shared_secondary' => '0',
        ];

        $this->repository->method('findTopCompatibleTriplets')
            ->willReturn([$row]);

        $result = $this->service->findTopTriplets(1);

        self::assertCount(1, $result);
        $triplet = $result[0];

        self::assertCount(3, $triplet['spices']);
        self::assertSame(6, $triplet['score']);

        self::assertSame(1, $triplet['spices'][0]['id']);
        self::assertSame(2, $triplet['spices'][1]['id']);
        self::assertSame(3, $triplet['spices'][2]['id']);
        self::assertSame('Cumin', $triplet['spices'][2]['name']);
    }

    public function testFindTopTripletsOutputContainsAllRequiredSpiceKeys(): void
    {
        $row = [
            's1_id' => '1',
            's1_name' => 'A',
            's1_file' => null,
            's1_color' => null,
            's1_group' => null,
            's2_id' => '2',
            's2_name' => 'B',
            's2_file' => null,
            's2_color' => null,
            's2_group' => null,
            's3_id' => '3',
            's3_name' => 'C',
            's3_file' => null,
            's3_color' => null,
            's3_group' => null,
            'score' => '9',
            'shared_main' => '3',
            'shared_secondary' => '0',
        ];

        $this->repository->method('findTopCompatibleTriplets')
            ->willReturn([$row]);
        $result = $this->service->findTopTriplets(1);

        foreach ($result[0]['spices'] as $spice) {
            self::assertArrayHasKey('id', $spice);
            self::assertArrayHasKey('name', $spice);
            self::assertArrayHasKey('file', $spice);
            self::assertArrayHasKey('color', $spice);
            self::assertArrayHasKey('groupName', $spice);
        }
    }
}
