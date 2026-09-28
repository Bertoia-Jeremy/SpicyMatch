<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use App\Enum\OdtMatrix;
use App\Exception\Match\InvalidMortarException;
use App\Factory\SpicyMatchFactory;
use App\Factory\SpicyMatchHistoryFactory;
use App\Repository\SpicesRepository;
use App\Service\SpicyMatchService;
use App\ValueObject\Match\CulinaryContext;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SpicyMatchServiceTest extends TestCase
{
    private SpicyMatchFactory&MockObject $factory;

    private SpicesRepository&MockObject $spicesRepo;

    private EntityManagerInterface&MockObject $em;

    private SpicyMatchService $service;

    private SpicyMatch $match;

    private Users $user;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(SpicyMatchFactory::class);
        $this->spicesRepo = $this->createMock(SpicesRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new SpicyMatchService(
            $this->factory,
            $this->spicesRepo,
            $this->em,
            new SpicyMatchHistoryFactory(),
        );
        $this->match = new SpicyMatch();
        $this->factory->method('create')
            ->willReturn($this->match);
        $this->user = new Users();
    }

    public function testPersistsMatchAndHistoryInOneFlush(): void
    {
        $this->spicesRepo->method('findBy')
            ->willReturn([new Spices()]);

        $persisted = [];
        $this->em->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity::class;
            });
        $this->em->expects(self::once())
            ->method('flush');

        $history = $this->service->start($this->user, [1], true, [], new CulinaryContext());

        self::assertSame([SpicyMatch::class, SpicyMatchHistory::class], $persisted);
        self::assertSame($this->match, $history->getSpicyMatch());
        self::assertSame($this->user, $this->match->getUser());
    }

    /**
     * @param list<int> $selectedIds
     * @param list<Spices> $found
     */
    #[DataProvider('emptyMortars')]
    public function testRefusesAnEmptyMortarWithoutWriting(array $selectedIds, array $found): void
    {
        $this->spicesRepo->method('findBy')
            ->willReturn($found);
        $this->em->expects(self::never())
            ->method('persist');
        $this->em->expects(self::never())
            ->method('flush');

        $this->expectException(InvalidMortarException::class);

        $this->service->start($this->user, $selectedIds, true, [], new CulinaryContext());
    }

    /**
     * @return iterable<string, array{list<int>, list<Spices>}>
     */
    public static function emptyMortars(): iterable
    {
        yield 'no id selected' => [[], []];
        yield 'ids unknown in database' => [[404, 405], []];
    }

    #[DataProvider('modes')]
    public function testStoresTheMode(bool $isManual): void
    {
        $this->spicesRepo->method('findBy')
            ->willReturn([new Spices()]);

        $this->service->start($this->user, [1], $isManual, [], new CulinaryContext());

        self::assertSame($isManual, $this->match->isManual());
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function modes(): iterable
    {
        yield 'manual' => [true];
        yield 'auto' => [false];
    }

    public function testBatchLoadsSelectedSpicesWithOneQuery(): void
    {
        $this->spicesRepo->expects(self::once())
            ->method('findBy')
            ->with([
                'id' => [1, 2, 3],
            ])
            ->willReturn([new Spices(), new Spices(), new Spices()]);

        $this->service->start($this->user, [1, 2, 3], true, [], new CulinaryContext());

        self::assertCount(3, $this->match->getSpices());
    }

    public function testManualModeDoesNotStoreResults(): void
    {
        $this->spicesRepo->expects(self::once())
            ->method('findBy')
            ->willReturn([new Spices()]);

        $this->service->start($this->user, [1], true, [[
            'id' => 99,
            'score' => 80,
        ]], new CulinaryContext());

        self::assertCount(0, $this->match->getResults());
    }

    public function testAutoModeWithoutCompatibleSpicesDoesNotQueryThem(): void
    {
        $this->spicesRepo->expects(self::once())
            ->method('findBy')
            ->willReturn([new Spices()]);

        $this->service->start($this->user, [1], false, [], new CulinaryContext());

        self::assertCount(0, $this->match->getResults());
    }

    public function testAutoModeStoresCompatibleResultsWithIntScores(): void
    {
        $compatibleSpice = $this->createStub(Spices::class);
        $compatibleSpice->method('getId')
            ->willReturn(7);

        $this->spicesRepo->method('findBy')
            ->willReturnOnConsecutiveCalls([new Spices()], [$compatibleSpice]);

        $this->service->start($this->user, [1], false, [[
            'id' => 7,
            'score' => '82',
        ]], new CulinaryContext());

        $results = $this->match->getResults()
            ->toArray();
        self::assertCount(1, $results);
        self::assertSame(82, $results[0]->getScore());
    }

    public function testCustomCulinaryContextIsPropagatedToMatch(): void
    {
        $this->spicesRepo->method('findBy')
            ->willReturn([new Spices()]);

        $ctx = new CulinaryContext(
            OdtMatrix::WATER,
            fatRatio: 0.25,
            waterRatio: 0.75,
            cookingTimeMin: 20,
            temperatureCelsius: 80,
        );

        $this->service->start($this->user, [1], false, [], $ctx);

        self::assertSame(OdtMatrix::WATER, $this->match->getMatrix());
        self::assertSame(0.25, $this->match->getFatRatio());
        self::assertSame(20, $this->match->getCookingTimeMin());
        self::assertSame(80, $this->match->getTemperatureCelsius());
    }
}
