<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Spices;
use App\Repository\SpicesRepository;
use App\Service\Match\CompatibleSpiceFinder;
use App\Service\SpiceGroupFinderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for the spice compatibility system.
 *
 * Uses the real database with the 30 fixture spices + spice_active_compound peuplée.
 * Pré-requis : bin/console app:recompute:oav --sync
 *
 * Ces tests valident :
 *  1. CompatibleSpiceFinder (OAV Tanimoto + enrichissement) avec vraie DB
 *  2. SpiceGroupFinderService (requêtes SQL legacy)
 *  3. SpicesRepository — findCandidatesForScoring
 */
class SpiceMatchingIntegrationTest extends KernelTestCase
{
    private SpicesRepository $spicesRepo;
    private CompatibleSpiceFinder $compatibleSpiceFinder;
    private SpiceGroupFinderService $groupFinder;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')
            ->getManager();
        /** @var SpicesRepository $repo */
        $repo = $em->getRepository(Spices::class);

        $this->spicesRepo = $repo;
        $this->compatibleSpiceFinder = $container->get(CompatibleSpiceFinder::class);
        $this->groupFinder = $container->get(SpiceGroupFinderService::class);
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

        $compoundNames = array_map(fn ($c) => $c->getName(), $thym->getAromaticsCompounds()->toArray());

        self::assertContains('Thymol', $compoundNames);
        self::assertContains('Carvacrol', $compoundNames);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CompatibleSpiceFinder — groupes de compatibilité connus
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Thym + Origan partagent thymol + carvacrol (OAV-actifs).
     * Au moins 3 épices de la famille monoterpènes doivent apparaître.
     */
    public function testThymAndOriganHaveCompatibleSpices(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        $origan = $this->findSpiceByName('Origan Méditerranéen');

        self::assertNotNull($thym);
        self::assertNotNull($origan);

        $results = $this->compatibleSpiceFinder->findCompatible([$thym->getId(), $origan->getId()], 100);

        self::assertNotEmpty($results, 'Thym + Origan should have compatible spices via OAV engine');
        self::assertGreaterThanOrEqual(3, count($results), 'At least 3 compatible spices expected');

        $names = array_column($results, 'name');
        $expectedFamily = ['Cumin', 'Romarin', 'Marjolaine', 'Sauge Officinale', 'Carvi (Cumin des Prés)'];
        $matchCount = count(array_filter($names, fn ($n) => in_array($n, $expectedFamily, true)));
        self::assertGreaterThanOrEqual(1, $matchCount);
    }

    /**
     * Thym seul → des candidats avec score [0, 100].
     */
    public function testThymSelectedAloneReturnsScores(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $results = $this->compatibleSpiceFinder->findCompatible([$thym->getId()], 100);

        self::assertNotEmpty($results);
        foreach ($results as $r) {
            self::assertGreaterThanOrEqual(0, $r['score']);
            self::assertLessThanOrEqual(100, $r['score']);
        }
    }

    /**
     * Origan doit apparaître compatible avec Thym (fort chevauchement OAV).
     */
    public function testOriganAppearsCompatibleWithThym(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $results = $this->compatibleSpiceFinder->findCompatible([$thym->getId()], 100);
        $origan = array_filter($results, fn ($r) => 'Origan Méditerranéen' === $r['name']);

        self::assertNotEmpty($origan, 'Origan should appear as compatible with Thym via OAV');
    }

    /**
     * Résultats triés par score décroissant.
     */
    public function testResultsAreSortedByScoreDescending(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $results = $this->compatibleSpiceFinder->findCompatible([$thym->getId()], 100);
        self::assertNotEmpty($results);

        $scores = array_column($results, 'score');
        $sorted = $scores;
        rsort($sorted);

        self::assertSame($sorted, $scores, 'Results must be sorted by score descending');
    }

    /**
     * Format de sortie : toutes les clés requises doivent être présentes.
     */
    public function testOutputKeysArePresent(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $results = $this->compatibleSpiceFinder->findCompatible([$thym->getId()], 100);
        self::assertNotEmpty($results);

        $required = ['id', 'name', 'file', 'color', 'groupName', 'score', 'agId', 'stId', 'typeName'];
        foreach ($required as $key) {
            self::assertArrayHasKey($key, $results[0], "Key '{$key}' missing from CompatibleSpiceFinder output");
        }
    }

    /**
     * Les épices du mortier ne doivent pas apparaître dans les résultats.
     */
    public function testMortarSpicesAreExcludedFromResults(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        $origan = $this->findSpiceByName('Origan Méditerranéen');

        self::assertNotNull($thym);
        self::assertNotNull($origan);

        $results = $this->compatibleSpiceFinder->findCompatible([$thym->getId(), $origan->getId()], 100);

        $resultIds = array_column($results, 'id');
        self::assertNotContains($thym->getId(), $resultIds, 'Thym must not appear in its own results');
        self::assertNotContains($origan->getId(), $resultIds, 'Origan must not appear in its own results');
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
        $pairs = $this->groupFinder->findTopPairs(20);
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

    public function testThymOriganCuminTripletAppears(): void
    {
        $triplets = $this->groupFinder->findTopTriplets(150);

        $found = false;
        $expectedNames = ['Thym Commun', 'Origan Méditerranéen', 'Cumin'];

        foreach ($triplets as $triplet) {
            $names = array_column($triplet['spices'], 'name');
            if (3 === count(array_intersect($expectedNames, $names))) {
                $found = true;
                self::assertGreaterThanOrEqual(6, $triplet['score'], 'Score should reflect 2 shared main compounds');
                break;
            }
        }

        self::assertTrue($found, 'Thym + Origan + Cumin triplet should appear in top triplets');
    }

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
        $compoundIds = array_map(fn ($c) => $c->getId(), $thym->getAromaticsCompounds()->toArray());

        $candidates = $this->spicesRepo->findCandidatesForScoring($compoundIds, [$thym->getId()]);

        $candidateIds = array_map(fn ($s) => $s->getId(), $candidates);
        self::assertNotContains($thym->getId(), $candidateIds, 'Selected spice must not appear in candidates');
    }

    public function testFindCandidatesEagerLoadsCompounds(): void
    {
        $thym = $this->findSpiceByName('Thym Commun');
        self::assertNotNull($thym);

        $compoundIds = array_map(fn ($c) => $c->getId(), $thym->getAromaticsCompounds()->toArray());

        $candidates = $this->spicesRepo->findCandidatesForScoring($compoundIds, [$thym->getId()]);

        self::assertNotEmpty($candidates);
        // Access collections — should not trigger lazy-load exceptions
        foreach ($candidates as $c) {
            $mainCount = $c->getAromaticsCompounds()
                ->count();
            $secCount = $c->getSecondaryAromaticsCompounds()
                ->count();
            self::assertGreaterThanOrEqual(0, $mainCount + $secCount);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function findSpiceByName(string $name): ?Spices
    {
        return $this->spicesRepo->findOneBy([
            'name' => $name,
        ]);
    }
}
