<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Entity\AromaticCompound;
use App\Entity\CompoundPhysical;
use App\Enum\OdtMatrix;
use App\Repository\CompoundPhysicalRepository;
use App\Service\Match\CookingTimelineBuilder;
use App\Service\Match\OavPartitionCalculator;
use App\ValueObject\Match\CulinaryContext;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class CookingTimelineBuilderTest extends TestCase
{
    private function makeBuilder(?CompoundPhysicalRepository $repo = null): CookingTimelineBuilder
    {
        return new CookingTimelineBuilder(
            $repo ?? $this->createStub(CompoundPhysicalRepository::class),
            new OavPartitionCalculator(),
        );
    }

    private function makeCompound(int $id, string $name): AromaticCompound
    {
        $compound = (new AromaticCompound())->setName($name);
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

    // ── Buckets vides ──────────────────────────────────────────────────────────

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

        $repo = $this->createStub(CompoundPhysicalRepository::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([]); // aucune donnée

        $buckets = $this->makeBuilder($repo)
            ->build([$c1, $c2], new CulinaryContext());

        self::assertCount(2, $buckets['unknown']);
        self::assertCount(0, $buckets['head']);
        self::assertCount(0, $buckets['heart']);
        self::assertCount(0, $buckets['base']);
    }

    // ── Classification correcte ───────────────────────────────────────────────

    public function testClassifiesByBoilingPoint(): void
    {
        // HEAD : bp=100 (limonène-like)
        // HEART : bp=200 (linalol-like)
        // BASE : bp=300 (eugenol-like)
        $head = $this->makeCompound(1, 'Limonene');
        $heart = $this->makeCompound(2, 'Linalol');
        $base = $this->makeCompound(3, 'Eugenol');

        $repo = $this->createStub(CompoundPhysicalRepository::class);
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
        self::assertSame('Limonene', $buckets['head'][0]['name']);
        self::assertSame('Linalol', $buckets['heart'][0]['name']);
        self::assertSame('Eugenol', $buckets['base'][0]['name']);
    }

    public function testKineticsValueExposedInEntry(): void
    {
        $c = $this->makeCompound(1, 'Z');
        $repo = $this->createStub(CompoundPhysicalRepository::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($c, logP: 0.0, bp: 100),
            ]);

        $buckets = $this->makeBuilder($repo)
            ->build([$c], new CulinaryContext());

        self::assertSame('head', $buckets['head'][0]['kinetics']);
    }

    // ── Rétention ──────────────────────────────────────────────────────────────

    public function testRetentionIsOneInNeutralContext(): void
    {
        // Contexte par défaut (pas de gras, pas de cuisson) → factor = 1
        $c = $this->makeCompound(1, 'X');
        $repo = $this->createStub(CompoundPhysicalRepository::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($c, logP: 0.0, bp: 100),
            ]);

        $buckets = $this->makeBuilder($repo)
            ->build([$c], new CulinaryContext());

        self::assertSame(1.0, $buckets['head'][0]['retention']);
    }

    public function testRetentionDecreasesUnderCooking(): void
    {
        // HEAD compound (bp=100) bouilli 30 min → rétention ≈ exp(-3) ≈ 0.05
        $c = $this->makeCompound(1, 'Volatile');
        $repo = $this->createStub(CompoundPhysicalRepository::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($c, logP: 0.0, bp: 100),
            ]);

        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 30, temperatureCelsius: 100);
        $buckets = $this->makeBuilder($repo)
            ->build([$c], $ctx);

        self::assertNotNull($buckets['head'][0]['retention']);
        self::assertLessThan(0.1, $buckets['head'][0]['retention']);
    }

    public function testRetentionIsNullForUnknownBucket(): void
    {
        $c = $this->makeCompound(1, 'X');
        $repo = $this->createStub(CompoundPhysicalRepository::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([]);

        $buckets = $this->makeBuilder($repo)
            ->build([$c], new CulinaryContext());

        self::assertNull($buckets['unknown'][0]['retention']);
        self::assertNull($buckets['unknown'][0]['kinetics']);
    }

    // ── Tri intra-bucket ──────────────────────────────────────────────────────

    public function testIntraBucketSortedByRetentionDesc(): void
    {
        // Trois HEAD compounds avec bp différents → différentes rétentions sous cuisson
        $a = $this->makeCompound(1, 'A_bp100'); // perd le plus
        $b = $this->makeCompound(2, 'B_bp140'); // intermédiaire
        $c = $this->makeCompound(3, 'C_bp148'); // perd le moins (proche de la limite HEAD)

        $repo = $this->createStub(CompoundPhysicalRepository::class);
        $repo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical($a, logP: 0.0, bp: 100),
                2 => $this->makePhysical($b, logP: 0.0, bp: 140),
                3 => $this->makePhysical($c, logP: 0.0, bp: 148),
            ]);

        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 30, temperatureCelsius: 100);
        $buckets = $this->makeBuilder($repo)
            ->build([$a, $b, $c], $ctx);

        // Rétention décroissante : C > B > A (bp plus haut = moins de perte)
        $names = array_column($buckets['head'], 'name');
        self::assertSame(['C_bp148', 'B_bp140', 'A_bp100'], $names);
    }

    public function testCompoundsWithoutIdAreSilentlyIgnored(): void
    {
        // Composé sans ID (jamais persisté) — ne doit pas crash la méthode
        $c = new AromaticCompound();
        $c->setName('Orphelin');

        $buckets = $this->makeBuilder()
            ->build([$c], new CulinaryContext());

        self::assertSame([], $buckets['head']);
        self::assertSame([], $buckets['unknown']);
    }
}
