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

        $response = $event->getResponse();

        // Only inject into HTML responses
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (! $user instanceof Users) {
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

        $content = $response->getContent();
        if ($content !== false) {
            $response->setContent(str_replace('</body>', $turboHtml . '</body>', $content));
        }
    }
}
