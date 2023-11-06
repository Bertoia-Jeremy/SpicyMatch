<?php

namespace App\Controller;

use App\Entity\Spices;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/epices')]
class SpicesController extends AbstractController
{
    #[Route('/', name: 'index_spices')]
    public function index(): Response
    {
        return $this->render('spices/index.html.twig', [
            'controller_name' => 'SpicesController',
        ]);
    }

    #[Route('/{id}', name: 'view_spice')]
    public function view(Spices $spice): Response
    {
        return $this->render('spices/view.html.twig', [
            'spice' => $spice,
        ]);
    }
}
