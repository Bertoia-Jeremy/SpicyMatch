<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/accessibilite', defaults: [
    '_locale' => 'fr',
])]
final class AccessibilityController extends AbstractController
{
    #[Route('', name: 'accessibility_declaration', methods: ['GET'])]
    public function declaration(): Response
    {
        return $this->render('accessibility/declaration.html.twig');
    }
}
