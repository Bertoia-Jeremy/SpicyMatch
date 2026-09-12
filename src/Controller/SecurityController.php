<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\RedirectTargetGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly RedirectTargetGuard $redirectTargetGuard,
    ) {
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $target = $this->redirectTargetGuard->safeOrNull($request->query->get('target'));

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'target' => $target,
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
    }
}
