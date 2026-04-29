<?php

namespace App\Controller;

use App\Entity\PreparationMethods;
use App\Repository\PreparationMethodsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/{id<\d+>}', name: 'view_preparation_methods', methods: ['GET'])]
    public function view(PreparationMethods $preparationMethod, Request $request): Response
    {
        // Seed the server-side timestamp for the "temps_de_l_infusion" easter egg
        // (stay ≥ 260s on the infusion page). Client cannot forge this value —
        // the EasterEggService reads it from session on validation.
        if (mb_strtolower((string) $preparationMethod->getName()) === 'infusion') {
            $session = $request->getSession();
            if (! \is_int($session->get('easter_egg.infusion_started_at'))) {
                $session->set('easter_egg.infusion_started_at', time());
            }
        }

        return $this->render('preparation_methods/view.html.twig', [
            'preparationMethod' => $preparationMethod,
        ]);
    }
}
