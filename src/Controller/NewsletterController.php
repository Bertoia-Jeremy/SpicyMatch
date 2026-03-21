<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users;
use App\Service\NewsletterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/newsletter')]
class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly NewsletterService $newsletterService,
    ) {
    }

    #[Route('/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): Response
    {
        $email = $request->request->getString('email');
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Adresse email invalide.');

            return $this->redirect($request->headers->get('referer', '/'));
        }

        if (! $this->isCsrfTokenValid('newsletter_subscribe', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirect($request->headers->get('referer', '/'));
        }

        /** @var Users|null $user */
        $user = $this->getUser();

        $this->newsletterService->subscribe($email, 'footer', $user, $request->getClientIp());
        $this->addFlash('success', 'Inscription à la newsletter confirmée !');

        return $this->redirect($request->headers->get('referer', '/'));
    }

    #[Route('/unsubscribe/{token}', name: 'newsletter_unsubscribe_link', methods: ['GET'])]
    public function unsubscribeByLink(string $token, Request $request): Response
    {
        $email = $request->query->getString('email');
        if ($email === '') {
            throw $this->createNotFoundException();
        }

        if (! $this->newsletterService->validateUnsubscribeToken($email, $token)) {
            throw $this->createNotFoundException();
        }

        $this->newsletterService->unsubscribe($email);

        return $this->render('newsletter/unsubscribed.html.twig', [
            'email' => $email,
        ]);
    }
}
