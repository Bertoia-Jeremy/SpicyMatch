<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Security\AltchaManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AltchaController extends AbstractController
{
    #[Route('/api/altcha/challenge', name: 'api_altcha_challenge', methods: ['GET'])]
    public function challenge(AltchaManager $altchaManager): JsonResponse
    {
        $response = $this->json($altchaManager->createChallenge());
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
