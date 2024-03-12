<?php

namespace App\Controller;

use App\Entity\SpicyMatch;
use App\Entity\Users;
use App\Repository\SpicesRepository;
use App\Service\SpicyMatchHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/spicymatch/history')]
class SpicyMatchHistoryController extends AbstractController
{
    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly SpicyMatchHistoryService $spicyMatchService
    ) {
    }

    #[Route('/', name: 'index_spicy_match_history', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        // Find by, Order id, Limit, Favorite on history?
        $histories = $user->getSpicyMatches();

        $spices = $this->spicyMatchService->getSpicesFromHistories($histories);

        return $this->render('spicy_match_history/index.html.twig', [
            'spicymatch_histories' => $histories,
            'spices' => $spices,
        ]);
    }

    #[Route('/{id}', name: 'view_spicy_match_history', methods: ['GET'])]
    public function view(SpicyMatch $spicyMatch): Response
    {
        $spices = $this->spicesRepository->findAllByStringIds($spicyMatch->getSpicesIds());

        return $this->render('spicy_match_history/view.html.twig', [
            'spicyMatch' => $spicyMatch,
            'spices' => $spices,
        ]);
    }
}
