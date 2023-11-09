<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/epices/composes_aromatiques')]
class AromaticCompoundController extends AbstractController
{
    #[Route('/', name: 'index_aromatic_compound')]
    public function index(): Response
    {
        return $this->render('aromatic_compound/index.html.twig', [
            'controller_name' => 'AromaticCompoundController',
        ]);
    }
}
