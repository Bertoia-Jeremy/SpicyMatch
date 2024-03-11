<?php

namespace App\Controller;

use App\Entity\PreparationMethods;
use App\Repository\PreparationMethodsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/preparation/methods')]
class PreparationMethodsController extends AbstractController
{
    #[Route('/', name: 'index_preparation_methods', methods: ['GET'])]
    public function index(PreparationMethodsRepository $preparationMethodsRepository): Response
    {
        return $this->render('preparation_methods/index.html.twig', [
            'preparationMethods' => $preparationMethodsRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'view_preparation_methods', methods: ['GET'])]
    public function view(PreparationMethods $preparationMethod): Response
    {
        return $this->render('preparation_methods/view.html.twig', [
            'preparationMethod' => $preparationMethod,
        ]);
    }
}
