<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Exception\Match\InvalidMortarException;
use App\Repository\SpicesRepository;
use App\Service\Match\MatchPipeline;
use App\ValueObject\Match\MortarIds;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint GET /api/match — Moteur de compatibilité aromatique.
 *
 * Paramètres :
 *   ?spices=id1,id2,…  (1 à 10 IDs d'épices, virgule-séparés)
 *   ?limit=20           (optionnel, défaut 20, max 100)
 *
 * Réponse 200 :
 * {
 *   "mortar": [1, 2],
 *   "results": [
 *     { "id": 14, "name": "Marjolaine", "score": 87 },
 *     ...
 *   ],
 *   "oav_mode": true,
 *   "count": 1
 * }
 *
 * Rate limit : 30 req/min par IP (sliding window).
 * Accès : PUBLIC_ACCESS — déclaré explicitement dans security.yaml.
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §4.1
 */
#[Route('/api/match', name: 'api_match', methods: ['GET'])]
final class MatchController extends AbstractController
{
    public function __construct(
        private readonly MatchPipeline $matchPipeline,
        private readonly SpicesRepository $spicesRepository,
        #[Autowire(service: 'limiter.match_api')]
        private readonly RateLimiterFactory $matchApiLimiter,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // Rate limiting : 30 req/min par IP (endpoint public, calcul SQL lourd).
        // getClientIp() peut retourner null si trusted_proxies n'est pas configuré.
        // Fallback 'unknown' partagerait un seul bucket entre tous les clients → DoS trivial.
        $clientIp = $request->getClientIp();
        if ($clientIp === null) {
            return $this->json(
                [
                    'error' => 'Impossible de déterminer l\'adresse IP du client.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $limiter = $this->matchApiLimiter->create('ip:' . $clientIp);
        $rateLimit = $limiter->consume();

        if (! $rateLimit->isAccepted()) {
            $retryAfter = max(1, $rateLimit->getRetryAfter()->getTimestamp() - time());

            return $this->json(
                [
                    'error' => 'Trop de requêtes. Réessayer dans ' . $retryAfter . ' secondes.',
                ],
                Response::HTTP_TOO_MANY_REQUESTS,
                [
                    'Retry-After' => (string) $retryAfter,
                ],
            );
        }

        // Validation du paramètre obligatoire
        $spicesParam = trim($request->query->getString('spices'));

        if ($spicesParam === '') {
            return $this->json([
                'error' => 'Le paramètre "spices" est requis (IDs virgule-séparés).',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Parsing puis validation domaine via MortarIds (count ∈ [1,10], IDs > 0, dédup).
        $parsedIds = array_map(static fn (string $id) => (int) trim($id), explode(',', $spicesParam));

        try {
            $mortar = new MortarIds($parsedIds);
        } catch (InvalidMortarException $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $limit = max(1, min(100, $request->query->getInt('limit', 20)));

        // Vérification que les épices du mortier existent et ne sont pas soft-deletées
        $mortarSpices = array_filter(
            $this->spicesRepository->findBy([
                'id' => $mortar->toArray(),
            ]),
            static fn ($s) => $s->getDeletedAt() === null,
        );
        $foundIds = array_map(static fn ($s) => $s->getId(), $mortarSpices);
        $missingIds = array_diff($mortar->toArray(), $foundIds);

        if ($missingIds !== []) {
            // Message générique — ne pas exposer quels IDs existent ou non (info disclosure)
            return $this->json([
                'error' => 'Une ou plusieurs épices sont introuvables.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Exécution du pipeline
        $pipelineResults = $this->matchPipeline->run($mortar, $limit);

        // Enrichissement avec les noms d'épices — DQL scalaire (pas d'hydratation entité)
        $candidateIds = array_column($pipelineResults, 'id');
        $nameMap = $this->spicesRepository->findNamesById($candidateIds);

        $results = array_map(
            static fn (array $row) => [
                'id' => $row['id'],
                'name' => $nameMap[$row['id']] ?? null,
                'score' => $row['score'],
            ],
            $pipelineResults
        );

        $oavMode = $pipelineResults !== [] && $pipelineResults[0]['oav_mode'];

        return $this->json([
            'mortar' => $mortar->toArray(),
            'results' => $results,
            'oav_mode' => $oavMode,
            'count' => count($results),
        ]);
    }
}
