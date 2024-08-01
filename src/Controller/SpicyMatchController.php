<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SpicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/spicymatch')]
class SpicyMatchController extends AbstractController
{
    #[Route('/', name: 'index_spicy_match')]
    public function index(SpicesRepository $spicesRepository): Response
    {
        return $this->render('spicy_match/index.html.twig', [
            'spices' => $spicesRepository->findAll(),
        ]);
    }
}
