<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/onboarding')]
#[IsGranted('ROLE_USER')]
class OnboardingController extends AbstractController
{
    public const ALLOWED_STATES = ['spices', 'lab', 'academy', 'done'];

    #[Route('/state', name: 'api_onboarding_state', methods: ['POST'])]
    public function saveState(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token', '');
        if (! $this->isCsrfTokenValid('onboarding', $token)) {
            return new JsonResponse([
                'error' => 'Invalid CSRF token',
            ], 403);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            $payload = [];
        }

        $state = $payload['state'] ?? null;

        if (null !== $state && ! in_array($state, self::ALLOWED_STATES, true)) {
            return new JsonResponse([
                'error' => 'Invalid state',
            ], 400);
        }

        /** @var Users $user */
        $user = $this->getUser();
        $user->setOnboardingState($state);
        $em->flush();

        return new JsonResponse([
            'state' => $state,
        ]);
    }
}
