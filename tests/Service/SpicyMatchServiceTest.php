<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\Users;
use App\Enum\OdtMatrix;
use App\Factory\SpicyMatchFactory;
use App\Repository\SpicesRepository;
use App\Service\SpicyMatchService;
use App\ValueObject\Match\CulinaryContext;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SpicyMatchService::createFromSelection().
 *
 * Verifies persistence delegation, batch loading, auto/manual mode branching.
 * Entity-level collection assertions (addSpice, addResult) belong to entity tests.
 */
#[AllowMockObjectsWithoutExpectations]
class SpicyMatchServiceTest extends TestCase
{
    private SpicyMatchFactory&MockObject $factory;
    private SpicesRepository&MockObject $spicesRepo;
    private EntityManagerInterface&MockObject $em;
    private SpicyMatchService $service;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(SpicyMatchFactory::class);
        $this->spicesRepo = $this->createMock(SpicesRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new SpicyMatchService($this->factory, $this->spicesRepo, $this->em);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Persistence contract
    // ──────────────────────────────────────────────────────────────────────────

    public function testPersistsAndFlushesTheCreatedMatch(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->em->expects(self::once())->method('persist')->with($match);
        $this->em->expects(self::once())->method('flush');

        $this->service->createFromSelection(null, [], true, []);
    }

    public function testReturnsThePersistableMatch(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $result = $this->service->createFromSelection(null, [], true, []);

        self::assertSame($match, $result);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // User and manual flag
    // ──────────────────────────────────────────────────────────────────────────

    public function testSetsNullUserOnMatch(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection(null, [], false, []);

        self::assertNull($match->getUser());
    }

    public function testSetsUserOnMatch(): void
    {
        $user = $this->createMock(Users::class);
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection($user, [], true, []);

        self::assertSame($user, $match->getUser());
    }

    public function testSetsIsManualTrueInManualMode(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection(null, [], true, []);

        self::assertTrue($match->isManual());
    }

    public function testSetsIsManualFalseInAutoMode(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection(null, [], false, []);

        self::assertFalse($match->isManual());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Batch loading of selected spices
    // ──────────────────────────────────────────────────────────────────────────

    public function testBatchLoadsSelectedSpicesWithOneQuery(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);

        $this->spicesRepo->expects(self::once())
            ->method('findBy')
            ->with([
                'id' => [1, 2, 3],
            ])
            ->willReturn([]);

        $this->service->createFromSelection(null, [1, 2, 3], true, []);
    }

    public function testAddsSelectedSpicesToMatch(): void
    {
        $spice1 = $this->createMock(Spices::class);
        $spice2 = $this->createMock(Spices::class);
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);

        $this->spicesRepo->method('findBy')
            ->willReturn([$spice1, $spice2]);

        $this->service->createFromSelection(null, [1, 2], true, []);

        self::assertCount(2, $match->getSpices());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Manual mode — no results stored
    // ──────────────────────────────────────────────────────────────────────────

    public function testManualModeDoesNotStoreResults(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        // Even if compatible spices are passed, manual mode must ignore them
        $compatible = [[
            'id' => 99,
            'score' => 80,
        ]];
        $this->service->createFromSelection(null, [], true, $compatible);

        self::assertCount(0, $match->getResults());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Auto mode — results stored
    // ──────────────────────────────────────────────────────────────────────────

    public function testAutoModeStoresCompatibleResults(): void
    {
        $compatibleSpice = $this->createMock(Spices::class);
        $compatibleSpice->method('getId')
            ->willReturn(99);

        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);

        // First findBy → selected spices (empty); second findBy → compatible
        $this->spicesRepo->method('findBy')
            ->willReturnOnConsecutiveCalls(
                [],                    // selected spices
                [$compatibleSpice],    // compatible spices
            );

        $compatible = [[
            'id' => 99,
            'score' => 75,
        ]];
        $this->service->createFromSelection(null, [], false, $compatible);

        self::assertCount(1, $match->getResults());
    }

    public function testAutoModeWithEmptyCompatibleSpicesDoesNotCallSecondFindBy(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);

        // Only 1 findBy call expected (selected spices); no second call for empty compatible list
        $this->spicesRepo->expects(self::once())
            ->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection(null, [], false, []);

        self::assertCount(0, $match->getResults());
    }

    public function testAutoModeResultScoresAreCastToInt(): void
    {
        $compatibleSpice = $this->createMock(Spices::class);
        $compatibleSpice->method('getId')
            ->willReturn(7);

        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);

        $this->spicesRepo->method('findBy')
            ->willReturnOnConsecutiveCalls([], [$compatibleSpice]);

        // score passed as string (as returned from DB queries)
        $compatible = [[
            'id' => 7,
            'score' => '82',
        ]];
        $this->service->createFromSelection(null, [], false, $compatible);

        $results = $match->getResults()
            ->toArray();
        self::assertCount(1, $results);
        self::assertSame(82, $results[0]->getScore());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Étape 3E-2 : persistance du contexte culinaire
    // ──────────────────────────────────────────────────────────────────────────

    public function testDefaultCulinaryContextPersistedOnMatch(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection(null, [], false, []);

        // Pas de ctx fourni → defaults (air, fat=0, time=0, temp=20)
        self::assertSame(OdtMatrix::AIR, $match->getMatrix());
        self::assertSame(0.0, $match->getFatRatio());
        self::assertSame(0, $match->getCookingTimeMin());
        self::assertSame(20, $match->getTemperatureCelsius());
    }

    public function testCustomCulinaryContextPersistedOnMatch(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $ctx = new CulinaryContext(
            OdtMatrix::WATER,
            fatRatio: 0.25,
            waterRatio: 0.75,
            cookingTimeMin: 20,
            temperatureCelsius: 80,
        );

        $this->service->createFromSelection(null, [], false, [], $ctx);

        self::assertSame(OdtMatrix::WATER, $match->getMatrix());
        self::assertSame(0.25, $match->getFatRatio());
        self::assertSame(20, $match->getCookingTimeMin());
        self::assertSame(80, $match->getTemperatureCelsius());
    }

    public function testCulinaryContextRoundtripsThroughGetCulinaryContext(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $ctx = new CulinaryContext(
            OdtMatrix::OIL,
            fatRatio: 1.0,
            waterRatio: 0.0,
            cookingTimeMin: 15,
            temperatureCelsius: 140
        );

        $this->service->createFromSelection(null, [], false, [], $ctx);
        $recovered = $match->getCulinaryContext();

        self::assertSame($ctx->matrix, $recovered->matrix);
        self::assertSame($ctx->fatRatio, $recovered->fatRatio);
        self::assertSame($ctx->cookingTimeMin, $recovered->cookingTimeMin);
        self::assertSame($ctx->temperatureCelsius, $recovered->temperatureCelsius);
    }
}
