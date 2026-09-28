<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Entity\AromaticCompound;
use App\Entity\CompoundPhysical;
use App\Enum\OdtMatrix;
use App\Repository\CandidateVetoRepository;
use App\Repository\CompoundPhysicalRepositoryInterface;
use App\Repository\SpiceActiveCompoundRepository;
use App\Service\Match\CorrectionApplier;
use App\Service\Match\FlavorGraphHybridizerInterface;
use App\Service\Match\MatchPipeline;
use App\Service\Match\MortarProfileBuilder;
use App\Service\Match\NullFlavorGraphHybridizer;
use App\Service\Match\OavPartitionCalculator;
use App\Service\Match\OavTanimotoScorer;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(MatchPipeline::class)]
final class MatchPipelineTest extends TestCase
{
    private function makePipeline(
        ?MortarProfileBuilder $builder = null,
        ?CandidateVetoRepository $veto = null,
        ?SpiceActiveCompoundRepository $repo = null,
        ?OavTanimotoScorer $scorer = null,
        ?CompoundPhysicalRepositoryInterface $physicalRepo = null,
        ?OavPartitionCalculator $calculator = null,
        ?FlavorGraphHybridizerInterface $hybridizer = null,
    ): MatchPipeline {
        $calculator ??= new OavPartitionCalculator();
        $physicalRepo ??= $this->createStub(CompoundPhysicalRepositoryInterface::class);

        return new MatchPipeline(
            $builder ?? $this->createStub(MortarProfileBuilder::class),
            $veto ?? $this->createStub(CandidateVetoRepository::class),
            $repo ?? $this->createStub(SpiceActiveCompoundRepository::class),
            $scorer ?? new OavTanimotoScorer(),
            $calculator,
            new CorrectionApplier($physicalRepo, $calculator),
            $hybridizer ?? new NullFlavorGraphHybridizer(),
        );
    }

    private function makePhysical(int $compoundId, ?float $logP = null, ?int $bp = null): CompoundPhysical
    {
        $compound = new AromaticCompound()
            ->setName('C' . $compoundId);
        $ref = new \ReflectionProperty(AromaticCompound::class, 'id');
        $ref->setValue($compound, $compoundId);

        $physical = new CompoundPhysical($compound);
        if ($logP !== null) {
            $physical->setLogP($logP);
        }
        if ($bp !== null) {
            $physical->setBoilingPointCelsius($bp);
        }

        return $physical;
    }

    public function testOavModeUsesOavVetoAndScores(): void
    {
        $mortarProfile = [
            1 => 100.0,
            2 => 50.0,
        ];

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 90.0,
                    2 => 40.0,
                ],
                11 => [
                    1 => 10.0,
                ],
            ]);

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn($mortarProfile);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10, 11]);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $results = $pipeline->run(new MortarIds([5]), limit: 20, ctx: new CulinaryContext());

        self::assertCount(2, $results);

        self::assertSame(10, $results[0]['id']);
        self::assertSame(96, $results[0]['score']);
        self::assertTrue($results[0]['oav_mode']);

        self::assertSame(11, $results[1]['id']);
        self::assertSame(27, $results[1]['score']);
    }

    public function testOavModeResultsSortedDescending(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                20 => [
                    1 => 2.0,
                ],
                21 => [
                    1 => 9.0,
                ],
                22 => [
                    1 => 5.0,
                ],
            ]);

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 10.0,
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([20, 21, 22]);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $results = $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext());

        $scores = array_column($results, 'score');
        $sorted = $scores;
        rsort($sorted);

        self::assertSame($sorted, $scores, 'Résultats doivent être triés par score décroissant');
    }

    public function testOavModeLimitApplied(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 5.0,
                ],
                11 => [
                    1 => 4.0,
                ],
                12 => [
                    1 => 3.0,
                ],
                13 => [
                    1 => 2.0,
                ],
                14 => [
                    1 => 1.5,
                ],
            ]);

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 10.0,
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10, 11, 12, 13, 14]);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $results = $pipeline->run(new MortarIds([1]), limit: 3, ctx: new CulinaryContext());

        self::assertCount(3, $results);
    }

    public function testOavModeLimit1ReturnsOnlyBestScorer(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 9.0,
                ],
                11 => [
                    1 => 2.0,
                ],
            ]);

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 10.0,
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10, 11]);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $results = $pipeline->run(new MortarIds([1]), limit: 1, ctx: new CulinaryContext());

        self::assertCount(1, $results);
        self::assertSame(10, $results[0]['id'], 'Le meilleur scorer doit être retourné avec limit:1');
    }

    public function testFallbackModeUsesPresenceVetoAndScoreZero(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);

        $veto = $this->createMock(CandidateVetoRepository::class);
        $veto->expects(self::once())
            ->method('findSurvivorsWithPresence')
            ->willReturn([30, 31]);
        $veto->expects(self::never())
            ->method('findSurvivors');

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn(null);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $results = $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext());

        self::assertCount(2, $results);
        foreach ($results as $r) {
            self::assertSame(0, $r['score']);
            self::assertFalse($r['oav_mode']);
        }
    }

    public function testFallbackModeLimitApplied(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivorsWithPresence')
            ->willReturn([30, 31, 32, 33, 34]);

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn(null);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $results = $pipeline->run(new MortarIds([1]), limit: 2, ctx: new CulinaryContext());

        self::assertCount(2, $results);
    }

    public function testReturnsEmptyWhenNoSurvivors(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([]);

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 5.0,
            ]);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $results = $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext());

        self::assertSame([], $results);
    }

    public function testFallbackReturnsEmptyWhenNoSurvivors(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivorsWithPresence')
            ->willReturn([]);

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn(null);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $results = $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext());

        self::assertSame([], $results);
    }

    public function testMissingSurvivorProfileIsSkipped(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 5.0,
                ],
            ]);

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 10.0,
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10, 99]);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $results = $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext());

        self::assertCount(1, $results);
        self::assertSame(10, $results[0]['id']);
        $candidate99 = array_first(array_filter($results, fn (array $r): bool => $r['id'] === 99)) ?? null;
        self::assertNull($candidate99, 'Candidat sans profil OAV doit être ignoré, pas scorer 0');
    }

    public function testRunPassesMatrixToMortarProfileBuilder(): void
    {
        $builder = $this->createMock(MortarProfileBuilder::class);
        $builder->expects(self::once())
            ->method('build')
            ->with(self::anything(), OdtMatrix::WATER)
            ->willReturn(null);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivorsWithPresence')
            ->willReturn([]);

        $pipeline = $this->makePipeline($builder, $veto);
        $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext(OdtMatrix::WATER));
    }

    public function testRunWithNeutralContextUsesAirMatrix(): void
    {
        $builder = $this->createMock(MortarProfileBuilder::class);
        $builder->expects(self::once())
            ->method('build')
            ->with(self::anything(), OdtMatrix::AIR)
            ->willReturn(null);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivorsWithPresence')
            ->willReturn([]);

        $pipeline = $this->makePipeline($builder, $veto);
        $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext());
    }

    public function testRunPassesMatrixToVetoRepository(): void
    {
        $mortarProfile = [
            1 => 5.0,
        ];

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn($mortarProfile);

        $veto = $this->createMock(CandidateVetoRepository::class);
        $veto->expects(self::once())
            ->method('findSurvivors')
            ->with(self::anything(), OdtMatrix::OIL)
            ->willReturn([]);

        $pipeline = $this->makePipeline($builder, $veto);
        $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext(OdtMatrix::OIL));
    }

    public function testRunPassesMatrixToSpiceActiveCompoundRepository(): void
    {
        $mortarProfile = [
            1 => 5.0,
        ];

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn($mortarProfile);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10]);

        $repo = $this->createMock(SpiceActiveCompoundRepository::class);
        $repo->expects(self::once())
            ->method('loadOavProfilesBatch')
            ->with([10], OdtMatrix::WATER)
            ->willReturn([]);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext(OdtMatrix::WATER));
    }

    public function testOavModeFlag(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 5.0,
                ],
            ]);

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 10.0,
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10]);

        $pipeline = $this->makePipeline($builder, $veto, $repo);
        $results = $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext());

        self::assertTrue($results[0]['oav_mode']);
    }

    public function testNeutralContextSkipsCompoundPhysicalLookup(): void
    {
        $physicalRepo = $this->createMock(CompoundPhysicalRepositoryInterface::class);
        $physicalRepo->expects(self::never())
            ->method('loadByCompoundIds');

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 10.0,
            ]);

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 5.0,
                ],
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10]);

        $pipeline = $this->makePipeline($builder, $veto, $repo, physicalRepo: $physicalRepo);
        $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext());
    }

    public function testExtendedContextTriggersCompoundPhysicalLookup(): void
    {
        $physicalRepo = $this->createMock(CompoundPhysicalRepositoryInterface::class);
        $physicalRepo->expects(self::once())
            ->method('loadByCompoundIds')
            ->willReturn([]);

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 10.0,
            ]);

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 5.0,
                ],
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10]);

        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.5, waterRatio: 0.5);

        $pipeline = $this->makePipeline($builder, $veto, $repo, physicalRepo: $physicalRepo);
        $pipeline->run(new MortarIds([1]), limit: 20, ctx: $ctx);
    }

    public function testCorrectionModifiesScoreForHydrophobicCompound(): void
    {
        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 10.0,
                2 => 10.0,
            ]);

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 10.0,
                    2 => 10.0,
                ],
                11 => [
                    1 => 10.0,
                ],
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10, 11]);

        $physicalRepo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $physicalRepo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical(1, logP: 4.0),
                2 => $this->makePhysical(2, logP: 0.0),
            ]);

        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.5, waterRatio: 0.5);

        $pipeline = $this->makePipeline(builder: $builder, veto: $veto, repo: $repo, physicalRepo: $physicalRepo);
        $results = $pipeline->run(new MortarIds([99]), limit: 20, ctx: $ctx);

        self::assertSame(10, $results[0]['id'], 'Le candidat équilibré doit l\'emporter dans une vinaigrette');
        self::assertGreaterThan($results[1]['score'], $results[0]['score']);
    }

    public function testCorrectionAppliesDecayAfterCooking(): void
    {
        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 10.0,
                2 => 10.0,
            ]);

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 10.0,
                ],
                11 => [
                    2 => 10.0,
                ],
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10, 11]);

        $physicalRepo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $physicalRepo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical(1, logP: 0.0, bp: 100),
                2 => $this->makePhysical(2, logP: 0.0, bp: 400),
            ]);

        $ctx = new CulinaryContext(OdtMatrix::WATER, cookingTimeMin: 60, temperatureCelsius: 100);

        $pipeline = $this->makePipeline(builder: $builder, veto: $veto, repo: $repo, physicalRepo: $physicalRepo);
        $results = $pipeline->run(new MortarIds([99]), limit: 20, ctx: $ctx);

        $resultsById = array_column($results, null, 'id');
        self::assertGreaterThan($resultsById[10]['score'], $resultsById[11]['score'], 'BASE survit > HEAD');
    }

    public function testCorrectionFallsBackGracefullyWhenPhysicalDataMissing(): void
    {
        $mortar = [
            1 => 10.0,
            2 => 5.0,
        ];
        $candidate = [
            1 => 8.0,
            2 => 4.0,
        ];

        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn($mortar);

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => $candidate,
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10]);

        $physicalRepo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $physicalRepo->method('loadByCompoundIds')
            ->willReturn([]);

        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.3, waterRatio: 0.7);

        $pipeline = $this->makePipeline(builder: $builder, veto: $veto, repo: $repo, physicalRepo: $physicalRepo);
        $resultsWithCtx = $pipeline->run(new MortarIds([99]), limit: 20, ctx: $ctx);

        $pipelineBaseline = $this->makePipeline(builder: $builder, veto: $veto, repo: $repo);
        $resultsBaseline = $pipelineBaseline->run(new MortarIds([99]), limit: 20, ctx: new CulinaryContext());

        self::assertSame(
            $resultsBaseline[0]['score'],
            $resultsWithCtx[0]['score'],
            'Sans donnée physique : pas de différence de score avec ou sans ctx étendu',
        );
    }
}
