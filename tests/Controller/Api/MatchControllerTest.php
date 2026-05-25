<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de l'endpoint GET /api/match.
 *
 * Utilise la DB de dev (spicymatch) avec les 30 épices fixture
 * et la table spice_active_compound peuplée (app:recompute:oav --sync requis).
 *
 * IDs connus d'après les fixtures :
 *   15 = Thym Commun, 16 = Origan Méditerranéen, 27 = Marjolaine
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §4.1
 */
final class MatchControllerTest extends WebTestCase
{
    // ── Validation des paramètres (400) ────────────────────────────────────────

    public function testMissingSpicesParamReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match');

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    public function testEmptySpicesParamReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=');

        self::assertResponseStatusCodeSame(400);
    }

    public function testNonPositiveIdsReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=0,-1,abc');

        // Tous les IDs invalides → count($mortarIds) < 1
        self::assertResponseStatusCodeSame(400);
    }

    public function testMoreThanTenIdsReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=1,2,3,4,5,6,7,8,9,10,11');

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
    }

    public function testExactlyTenIdsIsAccepted(): void
    {
        // 10 IDs valides → doit passer la validation (même si certains n'existent pas → 404)
        // On passe des IDs qui existent tous (1-10 sont des épices fixture)
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=1,2,3,4,5,6,7,8,9,10');

        // 200 ou 404 selon que les épices existent — pas 400
        self::assertNotSame(400, $client->getResponse()->getStatusCode());
    }

    public function testDuplicateIdsDeduped(): void
    {
        // "15,15,16" → dédupliqué en [15, 16] → 2 IDs valides → 200
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15,15,16');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(2, $data['mortar'], 'Les doublons doivent être dédupliqués');
    }

    // ── Épices introuvables (404) ──────────────────────────────────────────────

    public function testUnknownSpiceIdReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=99999');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        // Message générique — pas de disclosure d'IDs (cf. M2)
        self::assertArrayHasKey('error', $data);
    }

    public function testPartiallyUnknownSpicesReturn404(): void
    {
        // 15 existe, 99998 non → 404
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15,99998');

        self::assertResponseStatusCodeSame(404);
    }

    // ── Réponse 200 — structure ────────────────────────────────────────────────

    public function testValidRequestReturns200WithExpectedStructure(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('mortar', $data);
        self::assertArrayHasKey('results', $data);
        self::assertArrayHasKey('oav_mode', $data);
        self::assertArrayHasKey('count', $data);
        self::assertSame([15], $data['mortar']);
        self::assertSame(count($data['results']), $data['count']);
    }

    public function testResultItemsHaveExpectedKeys(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertNotEmpty($data['results'], 'Thym seul doit avoir des candidats');

        $first = $data['results'][0];
        self::assertArrayHasKey('id', $first);
        self::assertArrayHasKey('name', $first);
        self::assertArrayHasKey('score', $first);
        self::assertIsInt($first['id']);
        self::assertIsString($first['name']);
        self::assertIsInt($first['score']);
    }

    public function testResultsAreSortedByScoreDescending(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15,16');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        $scores = array_column($data['results'], 'score');
        $sorted = $scores;
        rsort($sorted);

        self::assertSame($sorted, $scores, 'Résultats doivent être triés par score décroissant');
    }

    public function testScoresAreBetween0And100(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15,16');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        foreach ($data['results'] as $result) {
            self::assertGreaterThanOrEqual(0, $result['score']);
            self::assertLessThanOrEqual(100, $result['score']);
        }
    }

    public function testOavModeTrueWhenDataAvailable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertTrue($data['oav_mode'], 'oav_mode doit être true (table spice_active_compound peuplée)');
    }

    // ── Paramètre limit ────────────────────────────────────────────────────────

    public function testLimitParamRestrictsResults(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15&limit=3');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertLessThanOrEqual(3, count($data['results']));
    }

    public function testLimitDefaultIs20(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertLessThanOrEqual(20, count($data['results']));
    }

    public function testLimitAbove100IsClampedTo100(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15&limit=999');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertLessThanOrEqual(100, count($data['results']));
    }

    // ── Cohérence sémantique ───────────────────────────────────────────────────

    public function testMortarSpicesNotInResults(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15,16');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        $resultIds = array_column($data['results'], 'id');
        self::assertNotContains(15, $resultIds, 'Thym ne doit pas apparaître dans les résultats');
        self::assertNotContains(16, $resultIds, 'Origan ne doit pas apparaître dans les résultats');
    }

    public function testThymOriganFindRelatedSpices(): void
    {
        // Thym + Origan partagent Thymol + Carvacrol → Marjolaine (27) doit apparaître
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15,16');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        $resultIds = array_column($data['results'], 'id');
        self::assertContains(27, $resultIds, 'Marjolaine doit être compatible avec Thym + Origan');
    }

    // ── Paramètre matrix ───────────────────────────────────────────────────────

    public function testInvalidMatrixReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15&matrix=steam');

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
        self::assertStringContainsString('Matrice invalide', $data['error']);
    }

    public function testResponseIncludesMatrixKey(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15&matrix=air');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertArrayHasKey('matrix', $data);
        self::assertSame('air', $data['matrix']);
    }

    public function testDefaultMatrixIsAirWhenOmitted(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertArrayHasKey('matrix', $data);
        self::assertSame('air', $data['matrix']);
    }

    public function testWaterMatrixIsAccepted(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15&matrix=water');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame('water', $data['matrix']);
    }

    public function testOilMatrixIsAccepted(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15&matrix=oil');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame('oil', $data['matrix']);
    }

    public function testEmptyMatrixReturns400(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/match?spices=15&matrix=');

        self::assertResponseStatusCodeSame(400);
    }
}
