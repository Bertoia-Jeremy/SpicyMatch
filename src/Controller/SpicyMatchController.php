<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SpicymatchHistory;
use App\Repository\SpicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/spicymatch')]
class SpicyMatchController extends AbstractController
{
    public function __construct(
        private SpicesRepository $spicesRepository
    ) {
    }

    #[Route('/', name: 'index_spicy_match')]
    public function index(): Response
    {
        return $this->render('spicy_match/index.html.twig', [
            'spices' => $this->spicesRepository->findAll(),
        ]);
    }

    #[Route('/view/{id}', name: 'view_spicy_match')]
    public function view(SpicymatchHistory $spicymatchHistory): Response
    {
        $spices = $this->spicesRepository->findAllByStringIds($spicymatchHistory->getSpicesIds());

        // Récupération des épices + conseils et tout le tintouin
        return $this->render('spicy_match/view.html.twig', [
            'spices' => $spices,
        ]);
    }
}
