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
use App\Service\Match\MatchPipeline;
use App\Service\Match\MortarProfileBuilder;
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
    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makePipeline(
        ?MortarProfileBuilder $builder = null,
        ?CandidateVetoRepository $veto = null,
        ?SpiceActiveCompoundRepository $repo = null,
        ?OavTanimotoScorer $scorer = null,
        ?CompoundPhysicalRepositoryInterface $physicalRepo = null,
        ?OavPartitionCalculator $calculator = null,
    ): MatchPipeline {
        $calculator ??= new OavPartitionCalculator();
        $physicalRepo ??= $this->createStub(CompoundPhysicalRepositoryInterface::class);

        return new MatchPipeline(
            $builder ?? $this->createStub(MortarProfileBuilder::class),
            $veto ?? $this->createStub(CandidateVetoRepository::class),
            $repo ?? $this->createStub(SpiceActiveCompoundRepository::class),
            $scorer ?? new OavTanimotoScorer(),
            $calculator,
            // L'applier réutilise les mocks injectés pour préserver les expects() des tests
            // (e.g. testExtendedContextTriggersCompoundPhysicalLookup vérifie loadByCompoundIds).
            new CorrectionApplier($physicalRepo, $calculator),
        );
    }

    private function makePhysical(int $compoundId, ?float $logP = null, ?int $bp = null): CompoundPhysical
    {
        $compound = (new AromaticCompound())->setName('C' . $compoundId);
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

    // ── Mode OAV ───────────────────────────────────────────────────────────────

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

        // Candidat 10 (log) : (ln90+ln40)/(ln100+ln50) = 8.18869/8.51719 ≈ 0.9614 → 96
        self::assertSame(10, $results[0]['id']);
        self::assertSame(96, $results[0]['score']);
        self::assertTrue($results[0]['oav_mode']);

        // Candidat 11 (log) : ln10/(ln100+ln50) = 2.30259/8.51719 ≈ 0.2703 → 27
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
                ],   // score faible
                21 => [
                    1 => 9.0,
                ],   // score élevé
                22 => [
                    1 => 5.0,
                ],   // score moyen
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

    // ── Mode fallback présence ─────────────────────────────────────────────────

    public function testFallbackModeUsesPresenceVetoAndScoreZero(): void
    {
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);

        // findSurvivorsWithPresence appelé (pas findSurvivors) — build() retourne null → mode dégradé
        $veto = $this->createMock(CandidateVetoRepository::class);
        $veto->expects(self::once())
            ->method('findSurvivorsWithPresence')
            ->willReturn([30, 31]);
        $veto->expects(self::never())
            ->method('findSurvivors');

        // build() retourne null → oavMode = false → fallback présence
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

    // ── Survivants vides ───────────────────────────────────────────────────────

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

    // ── Profil candidat absent de spice_active_compound ───────────────────────

    public function testMissingSurvivorProfileIsSkipped(): void
    {
        // Le veto retourne l'ID 99 mais loadOavProfilesBatch ne l'a pas (race condition rebuild).
        // Nouveau comportement : candidat 99 ignoré (score 0 avec oav_mode:true serait trompeur).
        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 5.0,
                ],
                // 99 absent
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

        // Candidat 10 présent, candidat 99 absent (pas de profil OAV → skipped)
        self::assertCount(1, $results);
        self::assertSame(10, $results[0]['id']);
        $candidate99 = array_values(array_filter($results, fn ($r) => $r['id'] === 99))[0] ?? null;
        self::assertNull($candidate99, 'Candidat sans profil OAV doit être ignoré, pas scorer 0');
    }

    // ── Propagation de CulinaryContext (matrice) ───────────────────────────────

    public function testRunPassesMatrixToMortarProfileBuilder(): void
    {
        $builder = $this->createMock(MortarProfileBuilder::class);
        $builder->expects(self::once())
            ->method('build')
            ->with(self::anything(), OdtMatrix::WATER)
            ->willReturn(null); // mode dégradé — pas besoin de veto/scorer

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivorsWithPresence')
            ->willReturn([]);

        $pipeline = $this->makePipeline($builder, $veto);
        $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext(OdtMatrix::WATER));
    }

    public function testRunWithNeutralContextUsesAirMatrix(): void
    {
        // CulinaryContext sans argument → matrix = AIR (sémantique neutre du VO).
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

    // ── Flag oav_mode ──────────────────────────────────────────────────────────

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

    // ── Correction physico-chimique ──────────────────────────────────────────

    public function testNeutralContextSkipsCompoundPhysicalLookup(): void
    {
        // Contexte par défaut (fat=0, cookingTime=0) → needsCorrection() = false
        // → CompoundPhysicalRepository::loadByCompoundIds() ne doit JAMAIS être appelé
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
        $pipeline->run(new MortarIds([1]), limit: 20, ctx: new CulinaryContext()); // ctx neutre
    }

    public function testExtendedContextTriggersCompoundPhysicalLookup(): void
    {
        // ctx avec fatRatio > 0 → needsCorrection() = true → batch lookup déclenché
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
        // Compound 1 = limonène-like (logP=4 → K_ow=10000), Compound 2 = polaire (logP=0 → K_ow=1).
        // Mortier OAV uniforme : {1: 10, 2: 10}, candidat {1: 10, 2: 10}.
        // En matrix=WATER pure : factor=1 partout → Tanimoto = 1.0 (score 100).
        // En matrix=WATER + fat=0.5 :
        //   compound 1 factor = 1/(10000×0.5+0.5) ≈ 0.0002 (limonène disparaît)
        //   compound 2 factor = 1/(1×0.5+0.5) = 1.0 (polaire reste)
        //   → profil corrigé {1: 0.002, 2: 10} pour mortier ET candidat → Tanimoto reste 1.0
        // Mais si candidat n'a QUE le compound 1 (volatil) : il s'éteint avec correction.
        $builder = $this->createStub(MortarProfileBuilder::class);
        $builder->method('build')
            ->willReturn([
                1 => 10.0,
                2 => 10.0,
            ]); // mortier équilibré

        $repo = $this->createStub(SpiceActiveCompoundRepository::class);
        $repo->method('loadOavProfilesBatch')
            ->willReturn([
                10 => [
                    1 => 10.0,
                    2 => 10.0,
                ], // candidat équilibré → match parfait baseline
                11 => [
                    1 => 10.0,
                ], // candidat ne contenant que le composé volatil
            ]);

        $veto = $this->createStub(CandidateVetoRepository::class);
        $veto->method('findSurvivors')
            ->willReturn([10, 11]);

        $physicalRepo = $this->createStub(CompoundPhysicalRepositoryInterface::class);
        $physicalRepo->method('loadByCompoundIds')
            ->willReturn([
                1 => $this->makePhysical(1, logP: 4.0), // hydrophobe (K_ow = 10 000)
                2 => $this->makePhysical(2, logP: 0.0), // neutre (K_ow = 1)
            ]);

        $ctx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.5, waterRatio: 0.5);

        $pipeline = $this->makePipeline(builder: $builder, veto: $veto, repo: $repo, physicalRepo: $physicalRepo);
        $results = $pipeline->run(new MortarIds([99]), limit: 20, ctx: $ctx);

        // Candidat 10 (équilibré) bat candidat 11 (composé volatil seul)
        self::assertSame(10, $results[0]['id'], 'Le candidat équilibré doit l\'emporter dans une vinaigrette');
        self::assertGreaterThan($results[1]['score'], $results[0]['score']);
    }

    public function testCorrectionAppliesDecayAfterCooking(): void
    {
        // Compound 1 = HEAD (bp=100), Compound 2 = BASE (bp=400).
        // Cuisson 60 min à 100°C → HEAD perd 99 % (exp(-0.1×60)≈0.0025), BASE garde ~98 %.
        // Mortier {1: 10, 2: 10}, candidat A = {1: 10} (HEAD only), candidat B = {2: 10} (BASE only).
        // Sans correction : Tanimoto identique pour A et B (= 1/2).
        // Avec correction : A s'éteint (compound 1 négligeable), B conserve une grande partie.
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
                ], // HEAD only
                11 => [
                    2 => 10.0,
                ], // BASE only
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

        // Le candidat BASE-only survit mieux à 60 min d'ébullition
        $resultsById = array_column($results, null, 'id');
        self::assertGreaterThan($resultsById[10]['score'], $resultsById[11]['score'], 'BASE survit > HEAD');
    }

    public function testCorrectionFallsBackGracefullyWhenPhysicalDataMissing(): void
    {
        // Aucun CompoundPhysical en BDD → factor=1 partout → score identique au baseline
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
            ->willReturn([]); // aucune donnée physique

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
