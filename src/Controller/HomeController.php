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
        // TODO => Voir pour faire un message d'acceuil à la premiere connexion journalière
        return $this->render(
            'home/index.html.twig',
            [
            ]
        );
    }
}
