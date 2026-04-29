<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PendingGamificationNotification;
use App\Entity\Users;
use App\Service\EasterEggService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/gamification')]
#[IsGranted('ROLE_USER')]
class EasterEggController extends AbstractController
{
    public function __construct(
        private readonly EasterEggService $easterEggService,
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'limiter.gamification_api')]
        private readonly RateLimiterFactory $gamificationApiLimiter,
    ) {
    }

    #[Route('/egg/{slug}', name: 'api_gamification_egg', methods: ['POST'])]
    public function found(string $slug, Request $request): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $limiter = $this->gamificationApiLimiter->create((string) $user->getId());
        if (! $limiter->consume()->isAccepted()) {
            return new JsonResponse([
                'error' => 'Too many requests',
            ], 429);
        }

        $token = $request->headers->get('X-CSRF-Token', '');
        if (! $this->isCsrfTokenValid('easter_egg', $token)) {
            return new JsonResponse([
                'error' => 'Invalid CSRF token',
            ], 403);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            $payload = [];
        }

        // 1. Handle egg
        $success = $this->easterEggService->handleEgg($user, $slug, $payload);

        if (! $success) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Invalid egg or conditions not met',
            ], 400);
        }

        // 2. Render Turbo Streams from pending notifications (same template as subscriber)
        if ($request->getPreferredFormat() === 'turbo_stream' || $request->headers->get(
            'Accept'
        ) === 'text/vnd.turbo-stream.html') {
            $notifications = $this->em->getRepository(PendingGamificationNotification::class)->findBy([
                'user' => $user,
                'deliveredAt' => null,
            ]);

            $html = '';
            foreach ($notifications as $notification) {
                $html .= $this->renderView('gamification/_notification_stream.html.twig', [
                    'type' => $notification->getType(),
                    'payload' => $notification->getPayload(),
                ]);
                $notification->markDelivered();
            }
            $this->em->flush();

            return new Response($html, 200, [
                'Content-Type' => 'text/vnd.turbo-stream.html',
            ]);
        }

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Egg found!',
        ]);
    }
}
