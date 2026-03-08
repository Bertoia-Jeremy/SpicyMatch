<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AlchemyFlavors;
use App\Repository\AlchemyFlavorsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/epices/saveurs_aromatiques')]
class AlchemyFlavorsController extends AbstractController
{
    #[Route('/', name: 'index_alchemy_flavors')]
    public function index(AlchemyFlavorsRepository $repository): Response
    {
        return $this->render('alchemy_flavors/index.html.twig', [
            'alchemyFlavors' => $repository->findAll(),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'view_alchemy_flavors')]
    public function view(AlchemyFlavors $alchemyFlavor): Response
    {
        return $this->render('alchemy_flavors/view.html.twig', [
            'alchemyFlavor' => $alchemyFlavor,
        ]);
    }
}
