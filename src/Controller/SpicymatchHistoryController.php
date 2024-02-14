<?php

namespace App\Controller;

use App\Entity\SpicymatchHistory;
use App\Form\SpicymatchHistoryType;
use App\Repository\SpicymatchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/spicymatch/history')]
class SpicymatchHistoryController extends AbstractController
{
    #[Route('/', name: 'app_spicymatch_history_index', methods: ['GET'])]
    public function index(SpicymatchHistoryRepository $spicymatchHistoryRepository): Response
    {
        return $this->render('spicymatch_history/index.html.twig', [
            'spicymatch_histories' => $spicymatchHistoryRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_spicymatch_history_show', methods: ['GET'])]
    public function show(SpicymatchHistory $spicymatchHistory): Response
    {
        return $this->render('spicymatch_history/show.html.twig', [
            'spicymatch_history' => $spicymatchHistory,
        ]);
    }
}
