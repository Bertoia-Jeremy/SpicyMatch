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

    public function testPersistsFlushesAndReturnsTheCreatedMatch(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->em->expects(self::once())->method('persist')->with($match);
        $this->em->expects(self::once())->method('flush');

        $result = $this->service->createFromSelection(null, [], true, [], new CulinaryContext());

        self::assertSame($match, $result);
    }

    public function testSetsNullUserOnMatch(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection(null, [], false, [], new CulinaryContext());

        self::assertNull($match->getUser());
    }

    public function testSetsUserOnMatch(): void
    {
        $user = $this->createStub(Users::class);
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection($user, [], true, [], new CulinaryContext());

        self::assertSame($user, $match->getUser());
    }

    public function testSetsIsManualTrueInManualMode(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection(null, [], true, [], new CulinaryContext());

        self::assertTrue($match->isManual());
    }

    public function testSetsIsManualFalseInAutoMode(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection(null, [], false, [], new CulinaryContext());

        self::assertFalse($match->isManual());
    }

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

        $this->service->createFromSelection(null, [1, 2, 3], true, [], new CulinaryContext());
    }

    public function testAddsSelectedSpicesToMatch(): void
    {
        $spice1 = $this->createStub(Spices::class);
        $spice2 = $this->createStub(Spices::class);
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);

        $this->spicesRepo->method('findBy')
            ->willReturn([$spice1, $spice2]);

        $this->service->createFromSelection(null, [1, 2], true, [], new CulinaryContext());

        self::assertCount(2, $match->getSpices());
    }

    public function testManualModeDoesNotStoreResults(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);
        $this->spicesRepo->method('findBy')
            ->willReturn([]);

        $compatible = [[
            'id' => 99,
            'score' => 80,
        ]];
        $this->service->createFromSelection(null, [], true, $compatible, new CulinaryContext());

        self::assertCount(0, $match->getResults());
    }

    public function testAutoModeStoresCompatibleResults(): void
    {
        $compatibleSpice = $this->createMock(Spices::class);
        $compatibleSpice->method('getId')
            ->willReturn(99);

        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);

        $this->spicesRepo->method('findBy')
            ->willReturnOnConsecutiveCalls(
                [],
                [$compatibleSpice],
            );

        $compatible = [[
            'id' => 99,
            'score' => 75,
        ]];
        $this->service->createFromSelection(null, [], false, $compatible, new CulinaryContext());

        self::assertCount(1, $match->getResults());
    }

    public function testAutoModeWithEmptyCompatibleSpicesDoesNotCallSecondFindBy(): void
    {
        $match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($match);

        $this->spicesRepo->expects(self::once())
            ->method('findBy')
            ->willReturn([]);

        $this->service->createFromSelection(null, [], false, [], new CulinaryContext());

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

        $compatible = [[
            'id' => 7,
            'score' => '82',
        ]];
        $this->service->createFromSelection(null, [], false, $compatible, new CulinaryContext());

        $results = $match->getResults()
            ->toArray();
        self::assertCount(1, $results);
        self::assertSame(82, $results[0]->getScore());
    }

    public function testCustomCulinaryContextIsPropagatedToMatch(): void
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
}
