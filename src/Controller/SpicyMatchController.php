<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/spicymatch', defaults: [
    '_locale' => 'fr',
])]
class SpicyMatchController extends AbstractController
{
    #[Route('/', name: 'index_spicy_match')]
    public function index(): Response
    {
        return $this->render('spicy_match/index.html.twig');
    }
}
