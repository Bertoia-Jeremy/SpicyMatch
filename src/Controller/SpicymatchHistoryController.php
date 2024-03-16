<?php

namespace App\Controller;

use App\Entity\CookingTips;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use App\Repository\CookingTipsRepository;
use App\Repository\PreparationTipsRepository;
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
        private readonly PreparationTipsRepository $preparationTipsRepository,
        private readonly CookingTipsRepository $cookingTipsRepository,
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
    public function view(SpicyMatchHistory $spicyMatchHistory): Response
    {
        $spices = $this->spicesRepository->findAllByStringIds($spicyMatchHistory->getSpicyMatchId()->getSpicesIds());
        $preparation = $this->preparationTipsRepository->findAllByStringIds($spicyMatchHistory->getPreparationTipsIds());
        $cookings = $this->cookingTipsRepository->findAllByStringIds($spicyMatchHistory->getCookingTipsIds());
        $cookingsByStep = [];

        /** @var CookingTips $cooking */
        foreach($cookings as $cooking){
            $cookingsByStep[$cooking->getStep()][] = $cooking;
        }

        return $this->render('spicy_match_history/view.html.twig', [
            'spices' => $spices,
            'preparations' => $preparation,
            'cookingsByStep' => $cookingsByStep,
        ]);
    }
}
