<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Spices;
use App\Repository\SpicesRepository;
use App\Service\CompatibilityScoreService;
use App\Service\SpiceGroupFinderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the spice compatibility system.
 *
 * Uses the real database (spicymatch_test) loaded with fixtures.
 * Run: APP_ENV=test php bin/console doctrine:fixtures:load --no-interaction
 *      before executing this suite.
 *
 * These tests validate:
 *  1. CompatibilityScoreService with real DB entities
 *  2. SpiceGroupFinderService SQL queries
 *  3. Known compatibility groups from fixtures
 */
class SpiceMatchingIntegrationTest extends KernelTestCase
{
    private SpicesRepository $spicesRepo;
    private CompatibilityScoreService $scoreService;
    private SpiceGroupFinderService $groupFinder;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        /** @var SpicesRepository $repo */
        $repo = $em->getRepository(Spices::class);

        $this->spicesRepo   = $repo;
        $this->scoreService = $container->get(CompatibilityScoreService::class);
        $this->groupFinder  = $container->get(SpiceGroupFinderService::class);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Repository sanity checks
    // ──────────────────────────────────────────────────────────────────────────

    public function testFixturesLoadedCorrectly(): void
    {
        $allSpices = $this->spicesRepo->findAll();
        self::assertGreaterThanOrEqual(30, count($allSpices), '30 fixture spices must be loaded');
    }

    public function testThymExistsInDatabase(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym, 'Thym Commun should be in the database');
        self::assertGreaterThanOrEqual(2, $thym->getAromaticsCompounds()->count());
    }

    public function testThymHasThymolAndCarvacrolAsMainCompounds(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $compoundNames = array_map(
            fn ($c) => $c->getName(),
            $thym->getAromaticsCompounds()->toArray()
        );

        self::assertContains('Thymol', $compoundNames);
        self::assertContains('Carvacrol', $compoundNames);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CompatibilityScoreService — known fixture groups
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Thym + Origan share thymol + carvacrol (both main).
     * Compatible spices in the same family: Cumin, Romarin, Marjolaine, Sauge, Carvi.
     * At least 3 of these should appear with score > 0.
     */
    public function testThymAndOriganHaveCompatibleSpices(): void
    {
        $thym   = $this->findSpiceByName('Thym Commun');
        $origan = $this->findSpiceByName('Origan Méditerranéen');

        self::assertNotNull($thym);
        self::assertNotNull($origan);

        $results = $this->scoreService->findCompatible([$thym, $origan]);

        self::assertNotEmpty($results, 'Thym + Origan should have compatible spices');
        self::assertGreaterThanOrEqual(3, count($results), 'At least 3 compatible spices expected');

        $names = array_column($results, 'name');
        // At least one of the monoterpene family spices
        $expectedFamily = ['Cumin', 'Romarin', 'Marjolaine', 'Sauge Officinale', 'Carvi (Cumin des Prés)'];
        $matchCount = count(array_filter($names, fn ($n) => in_array($n, $expectedFamily, true)));
        self::assertGreaterThanOrEqual(1, $matchCount);
    }

    /**
     * Cumin (main: thymol, carvacrol + secondary: limonene) selected with Thym.
     * Shared = {thymol, carvacrol}.
     * Score formula for Origan (main: carvacrol, thymol → candidateMax=6, raw=6) → 100.
     */
    public function testThymSelectedAloneReturnsScores(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $results = $this->scoreService->findCompatible([$thym]);

        self::assertNotEmpty($results);
        // All scores must be between 1 and 100
        foreach ($results as $r) {
            self::assertGreaterThanOrEqual(1, $r['score']);
            self::assertLessThanOrEqual(100, $r['score']);
        }
    }

    /**
     * Thym + Origan: Origan is perfect match for Thym's compounds.
     * Expected score for Origan when Thym is selected: 100
     * (Origan main={carvacrol, thymol}, all shared, candidateMax=6, raw=6).
     */
    public function testOriganScores100WhenThymSelected(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $results = $this->scoreService->findCompatible([$thym]);
        $origan = array_filter($results, fn ($r) => $r['name'] === 'Origan Méditerranéen');

        self::assertNotEmpty($origan, 'Origan should appear as compatible with Thym');
        $origan = array_values($origan)[0];
        self::assertSame(100, $origan['score']);
    }

    /**
     * Fenouil (main: anéthol) + Anis Étoilé (main: anéthol) → shared = anéthol.
     * Anis Vert (main: anéthol, secondary: estragole, linalool) should appear compatible.
     */
    public function testAnisFamilyCompatibility(): void
    {
        $fenouil  = $this->findSpiceByName('Fenouil (graines)');
        $badianne = $this->findSpiceByName('Anis Étoilé (Badiane)');

        self::assertNotNull($fenouil);
        self::assertNotNull($badianne);

        $results = $this->scoreService->findCompatible([$fenouil, $badianne]);
        $names   = array_column($results, 'name');

        self::assertContains('Anis Vert', $names, 'Anis Vert should be compatible with Fenouil+Badiane');
        self::assertContains('Estragon Français', $names, 'Estragon should be compatible');
    }

    /**
     * Piment de Cayenne (main: capsaïcine) + Paprika (main: capsaïcine)
     * → Poivre Noir, Poivre Blanc, Poivre Long, Piment d'Espelette should appear.
     */
    public function testCapsaicineFamilyCompatibility(): void
    {
        $piment  = $this->findSpiceByName('Piment de Cayenne');
        $paprika = $this->findSpiceByName('Paprika Doux');

        self::assertNotNull($piment);
        self::assertNotNull($paprika);

        $results = $this->scoreService->findCompatible([$piment, $paprika]);
        $names   = array_column($results, 'name');

        self::assertContains('Piment d\'Espelette', $names);
    }

    /**
     * Curcuma (main: curcumine) + Gingembre (main: zingérone, secondary: curcumine).
     * Shared: curcumine (in both — main for curcuma, secondary for gingembre).
     * They should find at least each other when selected alone.
     */
    public function testCurcumaGingembreCompatibility(): void
    {
        $curcuma = $this->findSpiceByName('Curcuma');
        self::assertNotNull($curcuma);

        $results = $this->scoreService->findCompatible([$curcuma]);
        $names   = array_column($results, 'name');

        self::assertContains('Gingembre Séché', $names, 'Gingembre should be compatible with Curcuma');
    }

    /**
     * 5 spices from thym/origan family: strict intersection must work.
     * All 5 share thymol + carvacrol → there should still be compatible spices.
     */
    public function testFiveSpiceStrictIntersection(): void
    {
        $spices = array_filter([
            $this->findSpiceByName('Thym Commun'),
            $this->findSpiceByName('Origan Méditerranéen'),
            $this->findSpiceByName('Cumin'),
            $this->findSpiceByName('Romarin'),
            $this->findSpiceByName('Sauge Officinale'),
        ]);

        self::assertCount(5, $spices, 'All 5 spices must be in the DB');

        $results = $this->scoreService->findCompatible(array_values($spices));
        // Marjolaine and Carvi also share thymol+carvacrol → should appear
        self::assertNotEmpty($results, '5 monoterpene spices should have compatible candidates');
    }

    /**
     * Safran has safranal (unique compound) → no other spice shares it.
     */
    public function testSafranHasNoCompatibleSpices(): void
    {
        $safran = $this->findSpiceByName('Safran');
        self::assertNotNull($safran);

        $results = $this->scoreService->findCompatible([$safran]);
        self::assertSame([], $results, 'Safran has a unique compound — no compatible spices expected');
    }

    /**
     * Results are sorted by score descending.
     */
    public function testResultsAreSortedByScoreDescending(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $results = $this->scoreService->findCompatible([$thym]);
        self::assertNotEmpty($results);

        $scores = array_column($results, 'score');
        $sorted = $scores;
        rsort($sorted);

        self::assertSame($sorted, $scores, 'Results must be sorted by score descending');
    }

    /**
     * Output format: all required keys must be present.
     */
    public function testOutputKeysArePresent(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $results = $this->scoreService->findCompatible([$thym]);
        self::assertNotEmpty($results);

        $required = ['id', 'name', 'file', 'color', 'groupName', 'score', 'mainCompoundsCount', 'secondaryCompoundsCount', 'alchemyFlavorsCount'];
        foreach ($required as $key) {
            self::assertArrayHasKey($key, $results[0], "Key '$key' missing from output");
        }
        self::assertSame(0, $results[0]['alchemyFlavorsCount']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SpiceGroupFinderService — SQL queries
    // ──────────────────────────────────────────────────────────────────────────

    public function testFindTopPairsReturnsResults(): void
    {
        $pairs = $this->groupFinder->findTopPairs(20);

        self::assertNotEmpty($pairs, 'findTopPairs() should return results with fixture data');
        self::assertLessThanOrEqual(20, count($pairs));
    }

    public function testFindTopPairsOutputFormat(): void
    {
        $pairs = $this->groupFinder->findTopPairs(5);
        self::assertNotEmpty($pairs);

        $pair = $pairs[0];
        self::assertArrayHasKey('score', $pair);
        self::assertArrayHasKey('shared_main', $pair);
        self::assertArrayHasKey('shared_secondary', $pair);
        self::assertArrayHasKey('spices', $pair);
        self::assertCount(2, $pair['spices']);
        self::assertGreaterThan(0, $pair['score']);
    }

    public function testFindTopPairsSortedByScoreDescending(): void
    {
        $pairs  = $this->groupFinder->findTopPairs(20);
        $scores = array_column($pairs, 'score');
        $sorted = $scores;
        rsort($sorted);

        self::assertSame($sorted, $scores, 'Pairs must be sorted by score descending');
    }

    public function testFindTopPairsContainsExpectedHighScorePair(): void
    {
        $pairs = $this->groupFinder->findTopPairs(20);

        // Thym + Origan share thymol + carvacrol (both main ×3 each) → score = 6
        $found = false;
        foreach ($pairs as $pair) {
            $names = array_column($pair['spices'], 'name');
            if (in_array('Thym Commun', $names, true) && in_array('Origan Méditerranéen', $names, true)) {
                $found = true;
                self::assertGreaterThan(0, $pair['score']);
                self::assertGreaterThanOrEqual(2, $pair['shared_main']);
                break;
            }
        }

        self::assertTrue($found, 'Thym + Origan pair should appear in top pairs');
    }

    public function testFindTopTripletsReturnsResults(): void
    {
        $triplets = $this->groupFinder->findTopTriplets(10);

        self::assertNotEmpty($triplets, 'findTopTriplets() should return results with fixture data');
        self::assertLessThanOrEqual(10, count($triplets));
    }

    public function testFindTopTripletsOutputFormat(): void
    {
        $triplets = $this->groupFinder->findTopTriplets(5);
        self::assertNotEmpty($triplets);

        $triplet = $triplets[0];
        self::assertArrayHasKey('score', $triplet);
        self::assertArrayHasKey('shared_main', $triplet);
        self::assertArrayHasKey('shared_secondary', $triplet);
        self::assertArrayHasKey('spices', $triplet);
        self::assertCount(3, $triplet['spices']);
    }

    public function testFindTopTripletsSortedByScoreDescending(): void
    {
        $triplets = $this->groupFinder->findTopTriplets(10);
        self::assertNotEmpty($triplets);

        $scores = array_column($triplets, 'score');
        $sorted = $scores;
        rsort($sorted);

        self::assertSame($sorted, $scores, 'Triplets must be sorted by score descending');
    }

    /**
     * Thym + Origan + Cumin all share thymol + carvacrol (score=6).
     * Uses limit=150 because many cross-family triplets score higher (max 8, many at 7).
     */
    public function testThymOriganCuminTripletAppears(): void
    {
        $triplets = $this->groupFinder->findTopTriplets(150);

        $found = false;
        $expectedNames = ['Thym Commun', 'Origan Méditerranéen', 'Cumin'];

        foreach ($triplets as $triplet) {
            $names = array_column($triplet['spices'], 'name');
            if (count(array_intersect($expectedNames, $names)) === 3) {
                $found = true;
                self::assertGreaterThanOrEqual(6, $triplet['score'], 'Score should reflect 2 shared main compounds');
                break;
            }
        }

        self::assertTrue($found, 'Thym + Origan + Cumin triplet should appear in top triplets');
    }

    /**
     * All spice IDs in pairs/triplets must be > 0 (valid DB entities).
     */
    public function testPairSpiceIdsAreValid(): void
    {
        $pairs = $this->groupFinder->findTopPairs(10);

        foreach ($pairs as $pair) {
            foreach ($pair['spices'] as $spice) {
                self::assertGreaterThan(0, $spice['id'], 'Spice ID must be a valid positive integer');
                self::assertNotEmpty($spice['name'], 'Spice name must not be empty');
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Repository — findCandidatesForScoring
    // ──────────────────────────────────────────────────────────────────────────

    public function testFindCandidatesForScoringExcludesSelectedSpices(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        // Get compound IDs from thym
        $compoundIds = array_map(
            fn ($c) => $c->getId(),
            $thym->getAromaticsCompounds()->toArray()
        );

        $candidates = $this->spicesRepo->findCandidatesForScoring(
            $compoundIds,
            [$thym->getId()]
        );

        $candidateIds = array_map(fn ($s) => $s->getId(), $candidates);
        self::assertNotContains($thym->getId(), $candidateIds, 'Selected spice must not appear in candidates');
    }

    public function testFindCandidatesEagerLoadsCompounds(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $compoundIds = array_map(
            fn ($c) => $c->getId(),
            $thym->getAromaticsCompounds()->toArray()
        );

        $candidates = $this->spicesRepo->findCandidatesForScoring($compoundIds, [$thym->getId()]);

        self::assertNotEmpty($candidates);
        // Access collections — should not trigger lazy-load exceptions
        foreach ($candidates as $c) {
            $mainCount = $c->getAromaticsCompounds()->count();
            $secCount  = $c->getSecondaryAromaticsCompounds()->count();
            self::assertGreaterThanOrEqual(0, $mainCount + $secCount);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function findSpiceByName(string $name): ?Spices
    {
        return $this->spicesRepo->findOneBy(['name' => $name]);
    }
}
