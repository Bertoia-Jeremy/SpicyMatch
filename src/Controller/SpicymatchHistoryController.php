<?php

namespace App\Controller;

use App\Entity\SpicymatchHistory;
use App\Entity\Users;
use App\Repository\SpicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/spicymatch/history')]
class SpicymatchHistoryController extends AbstractController
{
    public function __construct(private readonly SpicesRepository $spicesRepository){
    }

    #[Route('/', name: 'index_spicymatch_history', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        // Find by, Order id, Limit, Favorite on history?
        $histories = $user->getSpicymatchHistory();

        $spices = $this->getSpicesFromHistories($histories);
        
        return $this->render('spicymatch_history/index.html.twig', [
            'spicymatch_histories' => $histories,
            'spices' => $spices
        ]);
    }

    #[Route('/{id}', name: 'view_spicymatch_history', methods: ['GET'])]
    public function view(SpicymatchHistory $spicymatchHistory): Response
    {
        return $this->render('spicymatch_history/view.html.twig', [
            'spicymatch_history' => $spicymatchHistory,
        ]);
    }

    /**
     *
     * @param [SpicymatchHistory] $histories
     * @return array
     */
    private function getSpicesFromHistories($histories): array {
        $spicesHistoriesString = "";

        foreach($histories as $history){
            $spicesHistoriesString .=  $history->getSpicesIds(). ",";
        }

        $spicesHistoriesString = trim($spicesHistoriesString, ",");
        $spicesArray = $this->spicesRepository->findSpicesForMatch($spicesHistoriesString);

        $spices = [];
        foreach($spicesArray as $spice){
            $spices[$spice['id']] = $spice;
        }

        return $spices;
    }
}
