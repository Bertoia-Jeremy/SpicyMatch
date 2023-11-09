<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/epices/types_epices')]
class SpicyTypeController extends AbstractController
{
    #[Route('/', name: 'index_spicy_type')]
    public function index(): Response
    {
        return $this->render('spicy_type/index.html.twig', [
            'controller_name' => 'SpicyTypeController',
        ]);
    }

    #[Route('/{id}', name: 'view_spicy_type')]
    public function view(): Response
    {
        return $this->render('spicy_type/index.html.twig', [
            'controller_name' => 'SpicyTypeController',
        ]);
    }
}
