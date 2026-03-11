<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        $user = $this->getUser();

        if ($user && $user->getLastLoginAt()) {
            $today = new \DateTimeImmutable();
            $lastLogin = $user->getLastLoginAt();

            if ($lastLogin->format('Y-m-d') < $today->format('Y-m-d')) {
                $this->addFlash('info', 'Bienvenue de retour, ' . $user->getUserIdentifier() . '!');
            }
        } elseif ($user) {
            $this->addFlash('info', 'Bienvenue, ' . $user->getUserIdentifier() . '!');
        }

        return $this->render('home/index.html.twig', []);
    }
}
