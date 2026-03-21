<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SpicyType;
use App\Repository\SpicyTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/epices/types_epices')]
class SpicyTypeController extends AbstractController
{
    #[Route('/', name: 'index_spicy_type', methods: ['GET'])]
    public function index(SpicyTypeRepository $repository): Response
    {
        return $this->render('spicy_type/index.html.twig', [
            'spicyTypes' => $repository->findAll(),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'view_spicy_type', methods: ['GET'])]
    public function view(SpicyType $spicyType): Response
    {
        return $this->render('spicy_type/view.html.twig', [
            'spicyType' => $spicyType,
        ]);
    }
}
