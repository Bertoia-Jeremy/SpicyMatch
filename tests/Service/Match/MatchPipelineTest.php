<?php

declare(strict_types=1);

namespace App\Tests\Service\Match;

use App\Repository\CandidateVetoRepository;
use App\Repository\SpiceActiveCompoundRepository;
use App\Service\Match\MatchPipeline;
use App\Service\Match\MortarProfileBuilder;
use App\Service\Match\OavTanimotoScorer;
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
    ): MatchPipeline {
        return new MatchPipeline(
            $builder ?? $this->createStub(MortarProfileBuilder::class),
            $veto ?? $this->createStub(CandidateVetoRepository::class),
            $repo ?? $this->createStub(SpiceActiveCompoundRepository::class),
            $scorer ?? new OavTanimotoScorer(),
        );
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
        $results = $pipeline->run(new MortarIds([5]), limit: 20);

        self::assertCount(2, $results);

        // Candidat 10 : min(90,100)+min(40,50) / max(90,100)+max(40,50) = 130/150 ≈ 0.866 → 86
        self::assertSame(10, $results[0]['id']);
        self::assertSame(86, $results[0]['score']);
        self::assertTrue($results[0]['oav_mode']);

        // Candidat 11 : min(10,100)+min(0,50) / max(10,100)+max(0,50) = 10/150 ≈ 0.066 → 6
        self::assertSame(11, $results[1]['id']);
        self::assertSame(6, $results[1]['score']);
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
        $results = $pipeline->run(new MortarIds([1]));

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
        $results = $pipeline->run(new MortarIds([1]), limit: 3);

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
        $results = $pipeline->run(new MortarIds([1]), limit: 1);

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
        $results = $pipeline->run(new MortarIds([1]));

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
        $results = $pipeline->run(new MortarIds([1]), limit: 2);

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
        $results = $pipeline->run(new MortarIds([1]));

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
        $results = $pipeline->run(new MortarIds([1]));

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
        $results = $pipeline->run(new MortarIds([1]));

        // Candidat 10 présent, candidat 99 absent (pas de profil OAV → skipped)
        self::assertCount(1, $results);
        self::assertSame(10, $results[0]['id']);
        $candidate99 = array_values(array_filter($results, fn ($r) => $r['id'] === 99))[0] ?? null;
        self::assertNull($candidate99, 'Candidat sans profil OAV doit être ignoré, pas scorer 0');
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
        $results = $pipeline->run(new MortarIds([1]));

        self::assertTrue($results[0]['oav_mode']);
    }
}
