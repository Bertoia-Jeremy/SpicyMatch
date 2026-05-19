<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Users;
use App\Repository\PendingGamificationNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 * Injects pending gamification Turbo Streams into HTML responses.
 * Runs after the main response is built (priority -10).
 * Notifications are marked as delivered after injection.
 */
class GamificationNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly PendingGamificationNotificationRepository $notifRepository,
        private readonly EntityManagerInterface $em,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip Turbo Frame requests — their partial HTML is swapped into an existing frame,
        // any toast injected here would land inside the frame and be lost.
        if ($request->headers->get('Turbo-Frame') !== null) {
            return;
        }

        $response = $event->getResponse();

        // Only inject into HTML responses
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if ($content === false) {
            return;
        }

        // Must have a closing </body> to inject — abort silently otherwise, keep notifications pending.
        $bodyPos = strrpos($content, '</body>');
        if ($bodyPos === false) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (! $user instanceof Users) {
            return;
        }

        // Opt-out respected end-to-end: if the user has disabled gamification
        // we skip injection — pending notifications stay undelivered until
        // they re-enable it (or get pruned by cleanup command).
        if ($user->getProgression()?->isGamificationEnabled() === false) {
            return;
        }

        $notifications = $this->notifRepository->findUndeliveredForUser($user);
        if ($notifications === []) {
            return;
        }

        $turboHtml = '';
        foreach ($notifications as $notification) {
            $turboHtml .= $this->twig->render('gamification/_notification_stream.html.twig', [
                'type' => $notification->getType(),
                'payload' => $notification->getPayload(),
            ]);
            $notification->markDelivered();
        }

        $this->em->flush();

        $response->setContent(substr_replace($content, $turboHtml, $bodyPos, 0));
    }
}
