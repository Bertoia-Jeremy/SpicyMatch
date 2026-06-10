<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Enum\OdtMatrix;
use App\Exception\Match\InvalidMortarException;
use App\Repository\SpicesRepository;
use App\Service\Match\MatchConfidenceAssessorInterface;
use App\Service\Match\MatchPipelineInterface;
use App\ValueObject\Match\CulinaryContext;
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
 *   ?spices=id1,id2,…   (1 à 10 IDs d'épices, virgule-séparés)
 *   ?limit=20            (optionnel, défaut 20, max 100)
 *   ?matrix=air          (optionnel, défaut "air" ; valeurs : air|water|oil)
 *   ?fat=0.5             (optionnel, fraction grasse ∈ [0, 1] ; défaut 0)
 *   ?water=0.5           (optionnel, fraction aqueuse ∈ [0, 1] ; défaut 1-fat)
 *   ?cooking_time=30     (optionnel, minutes ≥ 0 ; défaut 0)
 *   ?temperature=100     (optionnel, °C ; défaut 20)
 *
 * Réponse 200 :
 * {
 *   "mortar": [1, 2],
 *   "results": [{ "id": 14, "name": "Marjolaine", "score": 87 }, …],
 *   "oav_mode": true,
 *   "matrix": "air",
 *   "fat_ratio": 0.0,
 *   "water_ratio": 1.0,
 *   "cooking_time_min": 0,
 *   "temperature_celsius": 20,
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
        private readonly MatchPipelineInterface $matchPipeline,
        private readonly SpicesRepository $spicesRepository,
        private readonly MatchConfidenceAssessorInterface $confidenceAssessor,
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

        // Contexte culinaire : fat ∈ [0,1] (water auto = 1-fat si absent), cooking_time min ≥ 0, temperature °C.
        $matrixRaw = $request->query->getString('matrix', 'air');

        try {
            $matrix = OdtMatrix::from(strtolower(trim($matrixRaw)));
        } catch (\ValueError) {
            return $this->json([
                'error' => 'Matrice invalide. Valeurs acceptées : air, water, oil.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation is_numeric + is_finite AVANT cast : (float)"1e308" → INF échappe sinon.
        // Bornes : constantes publiques de CulinaryContext (partagées API + UI + VO).
        $hasFat = $request->query->has('fat');
        $hasWater = $request->query->has('water');

        $fatRatio = 0.0;
        if ($hasFat) {
            $fatRaw = $request->query->get('fat', '');
            if (! is_numeric($fatRaw) || ! is_finite((float) $fatRaw)) {
                return $this->json([
                    'error' => 'Paramètre "fat" invalide (numérique fini ∈ [0, 1] attendu).',
                ], Response::HTTP_BAD_REQUEST);
            }
            $fatRatio = (float) $fatRaw;
            if ($fatRatio < CulinaryContext::FAT_RATIO_MIN || $fatRatio > CulinaryContext::FAT_RATIO_MAX) {
                return $this->json([
                    'error' => 'Paramètre "fat" hors plage (∈ [0, 1] attendu).',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $waterRatio = 1.0 - $fatRatio;
        if ($hasWater) {
            $waterRaw = $request->query->get('water', '');
            if (! is_numeric($waterRaw) || ! is_finite((float) $waterRaw)) {
                return $this->json([
                    'error' => 'Paramètre "water" invalide (numérique fini ∈ [0, 1] attendu).',
                ], Response::HTTP_BAD_REQUEST);
            }
            $waterRatio = (float) $waterRaw;
            if ($waterRatio < CulinaryContext::FAT_RATIO_MIN || $waterRatio > CulinaryContext::FAT_RATIO_MAX) {
                return $this->json([
                    'error' => 'Paramètre "water" hors plage (∈ [0, 1] attendu).',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $cookingTimeRaw = $request->query->get('cooking_time', '0');
        if (! is_numeric($cookingTimeRaw)) {
            return $this->json([
                'error' => 'Paramètre "cooking_time" invalide (entier ≥ 0 attendu).',
            ], Response::HTTP_BAD_REQUEST);
        }
        $cookingTime = (int) $cookingTimeRaw;
        if ($cookingTime < CulinaryContext::COOKING_TIME_MIN || $cookingTime > CulinaryContext::COOKING_TIME_MAX) {
            return $this->json([
                'error' => \sprintf(
                    'Paramètre "cooking_time" hors plage (∈ [0, %d] min attendu).',
                    CulinaryContext::COOKING_TIME_MAX,
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        $temperatureRaw = $request->query->get('temperature', '20');
        if (! is_numeric($temperatureRaw)) {
            return $this->json([
                'error' => 'Paramètre "temperature" invalide (entier ∈ [-50, 500] attendu).',
            ], Response::HTTP_BAD_REQUEST);
        }
        $temperature = (int) $temperatureRaw;
        if ($temperature < CulinaryContext::TEMPERATURE_MIN || $temperature > CulinaryContext::TEMPERATURE_MAX) {
            return $this->json([
                'error' => \sprintf(
                    'Paramètre "temperature" hors plage (∈ [%d, %d] °C attendu).',
                    CulinaryContext::TEMPERATURE_MIN,
                    CulinaryContext::TEMPERATURE_MAX,
                ),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $culinaryContext = new CulinaryContext($matrix, $fatRatio, $waterRatio, $cookingTime, $temperature);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'Paramètres culinaires invalides : ' . $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

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

        // Exécution du pipeline avec le contexte culinaire (matrice ODT)
        $pipelineResults = $this->matchPipeline->run($mortar, $limit, $culinaryContext);

        // Enrichissement avec les noms d'épices — DQL scalaire (pas d'hydratation entité)
        $candidateIds = array_column($pipelineResults, 'id');
        $nameMap = $this->spicesRepository->findNamesById($candidateIds, $request->getLocale());

        $results = array_map(
            static fn (array $row) => [
                'id' => $row['id'],
                'name' => $nameMap[$row['id']] ?? null,
                'score' => $row['score'],
            ],
            $pipelineResults
        );

        $oavMode = $pipelineResults !== [] && $pipelineResults[0]['oav_mode'];

        // Confiance globale = maillon le plus faible parmi les données contributrices.
        $confidence = $this->confidenceAssessor->assess($mortar, $culinaryContext->matrix);

        return $this->json([
            'mortar' => $mortar->toArray(),
            'results' => $results,
            'oav_mode' => $oavMode,
            'matrix' => $culinaryContext->matrix->value,
            'fat_ratio' => $culinaryContext->fatRatio,
            'water_ratio' => $culinaryContext->waterRatio,
            'cooking_time_min' => $culinaryContext->cookingTimeMin,
            'temperature_celsius' => $culinaryContext->temperatureCelsius,
            'confidence' => $confidence->value,
            'confidence_tier' => $confidence->tier(),
            'count' => count($results),
        ]);
    }
}
