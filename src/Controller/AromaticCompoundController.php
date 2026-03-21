<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AromaticCompound;
use App\Repository\AromaticCompoundRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/epices/composes_aromatiques')]
class AromaticCompoundController extends AbstractController
{
    #[Route('/', name: 'index_aromatic_compound')]
    public function index(AromaticCompoundRepository $repository): Response
    {
        return $this->render('aromatic_compound/index.html.twig', [
            'aromaticCompounds' => $repository->findAll(),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'view_aromatic_compound')]
    public function view(AromaticCompound $aromaticCompound): Response
    {
        return $this->render('aromatic_compound/view.html.twig', [
            'aromaticCompound' => $aromaticCompound,
        ]);
    }
}
