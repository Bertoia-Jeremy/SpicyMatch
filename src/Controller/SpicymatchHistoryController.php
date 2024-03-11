<?php

namespace App\Controller;

use App\Entity\SpicymatchHistory;
use App\Entity\Users;
use App\Repository\SpicesRepository;
use App\Service\SpicymatchHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/spicymatch/history')]
class SpicymatchHistoryController extends AbstractController
{
    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly SpicymatchHistoryService $spicymatchHistoryService
    ) {
    }

    #[Route('/', name: 'index_spicymatch_history', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        // Find by, Order id, Limit, Favorite on history?
        $histories = $user->getSpicymatchHistory();

        $spices = $this->spicymatchHistoryService->getSpicesFromHistories($histories);

        return $this->render('spicymatch_history/index.html.twig', [
            'spicymatch_histories' => $histories,
            'spices' => $spices,
        ]);
    }

    #[Route('/{id}', name: 'view_spicymatch_history', methods: ['GET'])]
    public function view(SpicymatchHistory $spicymatchHistory): Response
    {
        $spices = $this->spicesRepository->findAllByStringIds($spicymatchHistory->getSpicesIds());

        return $this->render('spicymatch_history/view.html.twig', [
            'history' => $spicymatchHistory,
            'spices' => $spices,
        ]);
    }
}
