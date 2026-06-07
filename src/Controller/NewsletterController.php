<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Users;
use App\Service\NewsletterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/newsletter')]
class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly NewsletterService $newsletterService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): Response
    {
        $email = $request->request->getString('email');
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', $this->translator->trans('flash.email_invalid'));

            return $this->redirect($this->safeReferer($request));
        }

        if (! $this->isCsrfTokenValid('newsletter_subscribe', $request->request->getString('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.csrf_invalid'));

            return $this->redirect($this->safeReferer($request));
        }

        /** @var Users|null $user */
        $user = $this->getUser();

        $this->newsletterService->subscribe($email, 'footer', $user, $request->getClientIp());
        $this->addFlash('success', $this->translator->trans('flash.newsletter_confirmed'));

        return $this->redirect($this->safeReferer($request));
    }

    /**
     * Referer interne uniquement (host exact), sinon `/` — empêche l'open redirect
     * via en-tête `Referer` forgé.
     */
    private function safeReferer(Request $request): string
    {
        $referer = $request->headers->get('referer');
        $base = $request->getSchemeAndHttpHost();
        if (is_string($referer) && ($referer === $base || str_starts_with($referer, $base . '/'))) {
            return $referer;
        }

        return '/';
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
