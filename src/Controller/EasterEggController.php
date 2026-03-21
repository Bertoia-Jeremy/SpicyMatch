<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PendingGamificationNotification;
use App\Entity\Users;
use App\Service\EasterEggService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/gamification')]
#[IsGranted('ROLE_USER')]
class EasterEggController extends AbstractController
{
    public function __construct(
        private readonly EasterEggService $easterEggService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/egg/{slug}', name: 'api_gamification_egg', methods: ['POST'])]
    public function found(string $slug, Request $request): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        $token = $request->headers->get('X-CSRF-Token', '');
        if (! $this->isCsrfTokenValid('easter_egg', $token)) {
            return new JsonResponse([
                'error' => 'Invalid CSRF token',
            ], 403);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable $e) {
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

        // 2. Check for notifications (processed synchronously via Messenger now)
        $notifications = $this->em->getRepository(PendingGamificationNotification::class)->findBy([
            'user' => $user,
        ]);

        // 3. Return Turbo Stream if requested
        if ($request->getPreferredFormat() === 'turbo_stream' || $request->headers->get(
            'Accept'
        ) === 'text/vnd.turbo-stream.html') {
            // Clean up notifications after rendering
            foreach ($notifications as $notification) {
                $this->em->remove($notification);
            }
            $this->em->flush();

            return $this->render('gamification/notification.stream.html.twig', [
                'notifications' => $notifications,
            ]);
        }

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Egg found!',
        ]);
    }
}
