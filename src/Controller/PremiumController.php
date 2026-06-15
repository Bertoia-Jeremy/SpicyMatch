<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/premium', defaults: [
    '_locale' => 'fr',
])]
final class PremiumController extends AbstractController
{
    #[Route('', name: 'premium', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('premium/index.html.twig');
    }
}
