<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Entity\AromaticCompound;
use App\Entity\CompoundPhysical;
use App\Enum\OdtMatrix;
use App\Repository\CompoundPhysicalRepositoryInterface;
use App\Service\Match\CookingTimelineBuilder;
use App\Service\Match\OavPartitionCalculator;
use App\ValueObject\Match\CulinaryContext;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[AllowMockObjectsWithoutExpectations]
final class CookingTimelineBuilderTest extends TestCase
{
    private function makeBuilder(?CompoundPhysicalRepositoryInterface $repo = null): CookingTimelineBuilder
    {
        return new CookingTimelineBuilder(
            $repo ?? $this->createStub(CompoundPhysicalRepositoryInterface::class),
            new OavPartitionCalculator(),
            new ArrayAdapter(),
        );
    }

    private function makeCompound(int $id, string $name): AromaticCompound
    {
        $compound = new AromaticCompound()
            ->setName($name);
        $ref = new \ReflectionProperty(AromaticCompound::class, 'id');
        $ref->setValue($compound, $id);

        return $compound;
    }

    private function makePhysical(AromaticCompound $compound, ?float $logP, ?int $bp): CompoundPhysical
    {
        $physical = new CompoundPhysical($compound);
        if ($logP !== null) {
            $physical->setLogP($logP);
        }
        if ($bp !== null) {
            $physical->setBoilingPointCelsius($bp);
        }

        return $physical;
    }

    public function testEmptyInputReturnsAllEmptyBuckets(): void
    {
        $buckets = $this->makeBuilder()
            ->build([], new CulinaryContext());

        self::assertSame([], $buckets['head']);
        self::assertSame([], $buckets['heart']);
        self::assertSame([], $buckets['base']);
        self::assertSame([], $buckets['unknown']);
    }

    public function testCompoundsWithoutPhysicalDataGoToUnknownBucket(): void
    {
        $c1 = $this->makeCompound(1, 'X');
        $c2 = $this->makeCompound(2, 'Y');

        $repo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([]);

        $buckets = $this->makeBuilder($repo)
            ->build([$c1, $c2], new CulinaryContext());

        self::assertCount(2, $buckets['unknown']);
        self::assertCount(0, $buckets['head']);
        self::assertCount(0, $buckets['heart']);
        self::assertCount(0, $buckets['base']);
    }

    public function testClassifiesByBoilingPoint(): void
    {
        $head = $this->makeCompound(1, 'Limonene');
        $heart = $this->makeCompound(2, 'Linalol');
        $base = $this->makeCompound(3, 'Eugenol');

        $repo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($head, logP: 4.0, bp: 100),
                2 => $this->makePhysical($heart, logP: 3.0, bp: 200),
                3 => $this->makePhysical($base, logP: 2.0, bp: 300),
            ]);

        $buckets = $this->makeBuilder($repo)
            ->build([$head, $heart, $base], new CulinaryContext());

        self::assertCount(1, $buckets['head']);
        self::assertCount(1, $buckets['heart']);
        self::assertCount(1, $buckets['base']);
        self::assertSame('Limonene', $buckets['head'][0]->name);
        self::assertSame('Linalol', $buckets['heart'][0]->name);
        self::assertSame('Eugenol', $buckets['base'][0]->name);
    }

    public function testKineticsValueExposedInEntry(): void
    {
        $c = $this->makeCompound(1, 'Z');
        $repo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($c, logP: 0.0, bp: 100),
            ]);

        $buckets = $this->makeBuilder($repo)
            ->build([$c], new CulinaryContext());

        self::assertSame('head', $buckets['head'][0]->kinetics);
    }

    public function testRetentionIsOneInNeutralContext(): void
    {
        $c = $this->makeCompound(1, 'X');
        $repo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($c, logP: 0.0, bp: 100),
            ]);

        $buckets = $this->makeBuilder($repo)
            ->build([$c], new CulinaryContext());

        self::assertSame(1.0, $buckets['head'][0]->retention);
    }

    public function testRetentionDecreasesUnderCooking(): void
    {
        $c = $this->makeCompound(1, 'Volatile');
        $repo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($c, logP: 0.0, bp: 100),
            ]);

        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 30, temperatureCelsius: 100);
        $buckets = $this->makeBuilder($repo)
            ->build([$c], $ctx);

        self::assertNotNull($buckets['head'][0]->retention);
        self::assertLessThan(0.1, $buckets['head'][0]->retention);
    }

    public function testRetentionIsNullForUnknownBucket(): void
    {
        $c = $this->makeCompound(1, 'X');
        $repo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([]);

        $buckets = $this->makeBuilder($repo)
            ->build([$c], new CulinaryContext());

        self::assertNull($buckets['unknown'][0]->retention);
        self::assertNull($buckets['unknown'][0]->kinetics);
    }

    public function testIntraBucketSortedByRetentionDesc(): void
    {
        $a = $this->makeCompound(1, 'A_bp100');
        $b = $this->makeCompound(2, 'B_bp140');
        $c = $this->makeCompound(3, 'C_bp148');

        $repo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($a, logP: 0.0, bp: 100),
                2 => $this->makePhysical($b, logP: 0.0, bp: 140),
                3 => $this->makePhysical($c, logP: 0.0, bp: 148),
            ]);

        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 30, temperatureCelsius: 100);
        $buckets = $this->makeBuilder($repo)
            ->build([$a, $b, $c], $ctx);

        $names = array_column($buckets['head'], 'name');
        self::assertSame(['C_bp148', 'B_bp140', 'A_bp100'], $names);
    }

    public function testBuildCachesResultsAcrossCalls(): void
    {
        $c = $this->makeCompound(1, 'X');
        $repo = $this->createMock(CompoundPhysicalRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($c, logP: 0.0, bp: 100),
            ]);

        $builder = $this->makeBuilder($repo);
        $ctx = new CulinaryContext();

        $first = $builder->build([$c], $ctx);
        $second = $builder->build([$c], $ctx);

        self::assertEquals($first, $second);
    }

    public function testBuildCacheKeyDiffersBetweenContexts(): void
    {
        $c = $this->makeCompound(1, 'X');
        $repo = $this->createMock(CompoundPhysicalRepositoryInterface::class);
        $repo->expects(self::exactly(2))
            ->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($c, logP: 0.0, bp: 100),
            ]);

        $builder = $this->makeBuilder($repo);

        $builder->build([$c], new CulinaryContext());
        $builder->build([$c], new CulinaryContext(cookingTimeMin: 10, temperatureCelsius: 100));
    }

    public function testCompoundsWithoutIdAreSilentlyIgnored(): void
    {
        $c = new AromaticCompound();
        $c->setName('Orphelin');

        $buckets = $this->makeBuilder()
            ->build([$c], new CulinaryContext());

        self::assertSame([], $buckets['head']);
        self::assertSame([], $buckets['unknown']);
    }
}
