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

        if ($request->headers->get('Turbo-Frame') !== null) {
            return;
        }

        $response = $event->getResponse();

        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();
        if ($content === false) {
            return;
        }

        $bodyPos = strrpos($content, '</body>');
        if ($bodyPos === false) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (! $user instanceof Users) {
            return;
        }

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
