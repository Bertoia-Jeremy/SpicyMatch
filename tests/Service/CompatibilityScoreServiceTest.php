<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AromaticCompound;
use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Repository\SpicesRepository;
use App\Service\CompatibilityScoreService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CompatibilityScoreService.
 *
 * All tests use mocks — no database involved.
 *
 * Formula under test (Jaccard-like, absolute 0-100):
 *   candidateMax = candidateMainCount×3 + candidateSecondaryCount×1
 *   raw          = sharedMainCount×3 + sharedSecondaryCount×1
 *   score        = min(100, round(raw / candidateMax × 100))
 */
#[AllowMockObjectsWithoutExpectations]
class CompatibilityScoreServiceTest extends TestCase
{
    private SpicesRepository&MockObject $repository;
    private CompatibilityScoreService $service;

    /**
     * @var array<int, AromaticCompound>
     */
    private array $compounds = [];

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SpicesRepository::class);
        $this->service = new CompatibilityScoreService($this->repository);

        // Pre-build 10 distinct compound mocks (id 1–10)
        for ($i = 1; $i <= 10; ++$i) {
            $c = $this->createMock(AromaticCompound::class);
            $c->method('getId')
                ->willReturn($i);
            $this->compounds[$i] = $c;
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Guard clauses
    // ──────────────────────────────────────────────────────────────────────────

    public function testEmptySelectionReturnsEmptyArray(): void
    {
        $result = $this->service->findCompatible([]);
        self::assertSame([], $result);
    }

    public function testNoSharedCompoundsReturnsEmptyArray(): void
    {
        // s1 has compound 1, s2 has compound 2 → no intersection
        $s1 = $this->makeSpice(1, main: [1], secondary: []);
        $s2 = $this->makeSpice(2, main: [2], secondary: []);

        // Repository should NOT be called when intersection is empty
        $this->repository->expects(self::never())->method('findCandidatesForScoring');

        $result = $this->service->findCompatible([$s1, $s2]);
        self::assertSame([], $result);
    }

    public function testSingleSpiceWithNoCandidatesReturnsEmptyArray(): void
    {
        $s1 = $this->makeSpice(1, main: [1, 2], secondary: []);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([]);

        $result = $this->service->findCompatible([$s1]);
        self::assertSame([], $result);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Score formula
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Candidate shares ALL its main compounds → score = 100.
     *
     * Selection shares compound {1, 2}.
     * Candidate has main: {1, 2} → candidateMax=6, raw=6 → 100%.
     */
    public function testPerfectMainMatchScores100(): void
    {
        $s1 = $this->makeSpice(1, main: [1, 2], secondary: []);
        $s2 = $this->makeSpice(2, main: [1, 2], secondary: []);

        $candidate = $this->makeSpice(99, main: [1, 2], secondary: []);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([$candidate]);

        $results = $this->service->findCompatible([$s1, $s2]);

        self::assertCount(1, $results);
        self::assertSame(100, $results[0]['score']);
    }

    /**
     * Candidate has 3 main compounds, 2 are shared → score ≈ 67%.
     *
     * shared={1,2}, candidate main={1,2,3}
     * candidateMax = 3×3 = 9
     * raw = 2×3 = 6
     * score = round(6/9×100) = 67
     */
    public function testPartialMainMatchScores67(): void
    {
        $s1 = $this->makeSpice(1, main: [1, 2], secondary: []);

        $candidate = $this->makeSpice(99, main: [1, 2, 3], secondary: []);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([$candidate]);

        $results = $this->service->findCompatible([$s1]);

        self::assertCount(1, $results);
        self::assertSame(67, $results[0]['score']);
    }

    /**
     * Secondary compound shared → weighs 1 (vs main ×3).
     *
     * shared={1} (main), candidate has main:{1}, secondary:{2,3}
     * candidateMax = 1×3 + 2×1 = 5
     * raw = 1×3 = 3
     * score = round(3/5×100) = 60
     */
    public function testSecondaryCompoundsWeighLessThanMain(): void
    {
        $s1 = $this->makeSpice(1, main: [1], secondary: []);

        $candidate = $this->makeSpice(99, main: [1], secondary: [2, 3]);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([$candidate]);

        $results = $this->service->findCompatible([$s1]);

        self::assertSame(60, $results[0]['score']);
        self::assertSame(1, $results[0]['mainCompoundsCount']);
        self::assertSame(0, $results[0]['secondaryCompoundsCount']);
    }

    /**
     * Shared secondary compound contributes 1 to raw.
     *
     * shared={2} (from secondary of selection).
     * candidate has secondary:{2}, main:{1}
     * candidateMax = 1×3 + 1×1 = 4
     * raw = 0×3 + 1×1 = 1
     * score = round(1/4×100) = 25
     */
    public function testSharedSecondaryCompoundContributesToScore(): void
    {
        // s1 has compound 2 as secondary — it still contributes to shared set
        $s1 = $this->makeSpice(1, main: [3], secondary: [2]);
        $s2 = $this->makeSpice(2, main: [3], secondary: [2]);

        // Candidate shares compound 2 (secondary in candidate)
        $candidate = $this->makeSpice(99, main: [1], secondary: [2]);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([$candidate]);

        $results = $this->service->findCompatible([$s1, $s2]);

        self::assertCount(1, $results);
        self::assertSame(0, $results[0]['mainCompoundsCount']);
        self::assertSame(1, $results[0]['secondaryCompoundsCount']);
        self::assertSame(25, $results[0]['score']);
    }

    /**
     * Score is capped at 100 even if raw > candidateMax (shouldn't happen but guard exists).
     */
    public function testScoreIsNeverAbove100(): void
    {
        $s1 = $this->makeSpice(1, main: [1, 2, 3], secondary: []);

        // Candidate with fewer compounds than shared set (edge case)
        $candidate = $this->makeSpice(99, main: [1], secondary: []);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([$candidate]);

        $results = $this->service->findCompatible([$s1]);
        self::assertLessThanOrEqual(100, $results[0]['score']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Intersection logic
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Strict intersection: compound must be in ALL selected spices.
     *
     * s1 has {1,2}, s2 has {2,3} → shared = {2} only.
     */
    public function testStrictIntersectionWithTwoSpices(): void
    {
        $s1 = $this->makeSpice(1, main: [1, 2], secondary: []);
        $s2 = $this->makeSpice(2, main: [2, 3], secondary: []);

        $this->repository
            ->expects(self::once())
            ->method('findCandidatesForScoring')
            ->with(
                self::callback(fn (array $ids) => $ids === [2]),  // only compound 2 shared
                self::anything()
            )
            ->willReturn([]);

        $this->service->findCompatible([$s1, $s2]);
    }

    /**
     * 5 spices: only compound shared by all 5 qualifies.
     *
     * All 5 share compound 1. Only s1..s4 also share compound 2.
     * → shared = {1} only.
     */
    public function testFiveSpicesStrictIntersection(): void
    {
        $spices = [
            $this->makeSpice(1, main: [1, 2], secondary: []),
            $this->makeSpice(2, main: [1, 2], secondary: []),
            $this->makeSpice(3, main: [1, 2], secondary: []),
            $this->makeSpice(4, main: [1, 2], secondary: []),
            $this->makeSpice(5, main: [1], secondary: []),  // compound 2 absent
        ];

        $this->repository
            ->expects(self::once())
            ->method('findCandidatesForScoring')
            ->with(self::callback(fn (array $ids) => $ids === [1]), self::anything())
            ->willReturn([]);

        $this->service->findCompatible($spices);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Output format
    // ──────────────────────────────────────────────────────────────────────────

    public function testOutputContainsRequiredKeys(): void
    {
        $s1 = $this->makeSpice(1, main: [1], secondary: []);
        $candidate = $this->makeSpice(99, main: [1], secondary: [], withGroup: true);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([$candidate]);

        $results = $this->service->findCompatible([$s1]);

        self::assertArrayHasKey('id', $results[0]);
        self::assertArrayHasKey('name', $results[0]);
        self::assertArrayHasKey('file', $results[0]);
        self::assertArrayHasKey('color', $results[0]);
        self::assertArrayHasKey('groupName', $results[0]);
        self::assertArrayHasKey('score', $results[0]);
        self::assertArrayHasKey('mainCompoundsCount', $results[0]);
        self::assertArrayHasKey('secondaryCompoundsCount', $results[0]);
        self::assertArrayHasKey('alchemyFlavorsCount', $results[0]);
    }

    public function testAlchemyFlavorsCountIsAlwaysZero(): void
    {
        $s1 = $this->makeSpice(1, main: [1], secondary: []);
        $candidate = $this->makeSpice(99, main: [1], secondary: []);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([$candidate]);

        $results = $this->service->findCompatible([$s1]);
        self::assertSame(0, $results[0]['alchemyFlavorsCount']);
    }

    /**
     * Results must be sorted by score descending.
     */
    public function testResultsSortedByScoreDescending(): void
    {
        $s1 = $this->makeSpice(1, main: [1, 2], secondary: []);

        // Candidate A: shares 1 out of 2 main → 50%
        $candidateA = $this->makeSpice(10, main: [1, 3], secondary: []);
        // Candidate B: shares 2 out of 2 main → 100%
        $candidateB = $this->makeSpice(11, main: [1, 2], secondary: []);
        // Candidate C: shares 1 out of 3 main → 33%
        $candidateC = $this->makeSpice(12, main: [1, 4, 5], secondary: []);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([$candidateA, $candidateC, $candidateB]);  // intentionally unordered

        $results = $this->service->findCompatible([$s1]);

        self::assertCount(3, $results);
        self::assertSame(100, $results[0]['score']);
        self::assertSame(50, $results[1]['score']);
        self::assertSame(33, $results[2]['score']);
    }

    /**
     * Selected spices' IDs are passed as exclusion list to the repository.
     */
    public function testSelectedSpicesAreExcludedFromCandidates(): void
    {
        $s1 = $this->makeSpice(5, main: [1], secondary: []);
        $s2 = $this->makeSpice(7, main: [1], secondary: []);

        $this->repository
            ->expects(self::once())
            ->method('findCandidatesForScoring')
            ->with(
                self::anything(),
                self::callback(fn (array $ids) => in_array(5, $ids, true) && in_array(7, $ids, true))
            )
            ->willReturn([]);

        $this->service->findCompatible([$s1, $s2]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Real-world scenario: Thym + Origan family
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Thym (main: thymol=1, carvacrol=2) + Origan (main: carvacrol=2, thymol=1)
     * → shared = {1, 2}
     * Cumin (main: thymol=1, carvacrol=2, secondary: limonene=3) as candidate
     * candidateMax = 2×3 + 1 = 7, raw = 2×3 = 6
     * score = round(6/7×100) = 86.
     */
    public function testThymOriganFamilyScenario(): void
    {
        $thym = $this->makeSpice(1, main: [1, 2], secondary: []);
        $origan = $this->makeSpice(2, main: [2, 1], secondary: []);
        $cumin = $this->makeSpice(3, main: [1, 2], secondary: [3]);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([$cumin]);

        $results = $this->service->findCompatible([$thym, $origan]);

        self::assertCount(1, $results);
        self::assertSame(86, $results[0]['score']);
        self::assertSame(2, $results[0]['mainCompoundsCount']);
        self::assertSame(0, $results[0]['secondaryCompoundsCount']);
    }

    /**
     * Piment (main: capsaicine=5) + Paprika (main: capsaicine=5, secondary: piperine=6)
     * → shared = {5} (strict: capsaicine in both)
     * Poivre (main: piperine=6, secondary: limonene=7) as candidate
     * shared compound 5 is NOT in Poivre → score = 0 → excluded.
     */
    public function testCandidateWithNoSharedCompoundExcluded(): void
    {
        $piment = $this->makeSpice(1, main: [5], secondary: []);
        $paprika = $this->makeSpice(2, main: [5], secondary: [6]);
        $poivre = $this->makeSpice(3, main: [6], secondary: [7]);

        $this->repository
            ->method('findCandidatesForScoring')
            ->willReturn([$poivre]);  // repo returns it, service should score 0 and exclude

        $results = $this->service->findCompatible([$piment, $paprika]);
        self::assertSame([], $results);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param int[] $main
     * @param int[] $secondary
     */
    private function makeSpice(
        int $id,
        array $main,
        array $secondary,
        bool $withGroup = false,
    ): Spices&MockObject {
        $spice = $this->createMock(Spices::class);
        $spice->method('getId')
            ->willReturn($id);
        $spice->method('getName')
            ->willReturn('Spice #' . $id);
        $spice->method('getFile')
            ->willReturn(null);

        if ($withGroup) {
            $group = $this->createMock(AromaticGroups::class);
            $group->method('getColor')
                ->willReturn('#ff0000');
            $group->method('getName')
                ->willReturn('Test Group');
            $spice->method('getAromaticGroups')
                ->willReturn($group);
        } else {
            $spice->method('getAromaticGroups')
                ->willReturn(null);
        }

        $mainCollection = new ArrayCollection(
            array_map(fn (int $cid) => $this->compounds[$cid], $main)
        );
        $secCollection = new ArrayCollection(
            array_map(fn (int $cid) => $this->compounds[$cid], $secondary)
        );

        $spice->method('getAromaticsCompounds')
            ->willReturn($mainCollection);
        $spice->method('getSecondaryAromaticsCompounds')
            ->willReturn($secCollection);

        return $spice;
    }
}
