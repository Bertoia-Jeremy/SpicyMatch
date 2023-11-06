<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/epices/saveurs_aromatiques')]
class AlchemyFlavorsController extends AbstractController
{
    #[Route('/', name: 'index_alchemy_flavors')]
    public function index(): Response
    {
        return $this->render('alchemy_flavors/index.html.twig', [
            'controller_name' => 'AlchemyFlavorsController',
        ]);
    }
}
