<?php

namespace App\Controller;

use App\Entity\CookingTips;
use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use App\Repository\CookingTipsRepository;
use App\Repository\PreparationTipsRepository;
use App\Repository\SpicesRepository;
use App\Service\SpicyMatchHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/view/{id}', name: 'view_spicy_match_history', methods: ['GET'])]
    public function view(SpicyMatchHistory $spicyMatchHistory): Response
    {
        $spices = $this->spicesRepository->findAllByStringIds($spicyMatchHistory->getSpicyMatchId()->getSpicesIds());
        $preparation = $this->preparationTipsRepository->findAllByStringIds(
            $spicyMatchHistory->getPreparationTipsIds()
        );
        $cookings = $this->cookingTipsRepository->findAllByStringIds($spicyMatchHistory->getCookingTipsIds());
        $cookingsByStep = [
            0 => [],
            1 => [],
            2 => [],
            3 => [],
            4 => [],
        ];

        /** @var CookingTips $cooking */
        foreach ($cookings as $cooking) {
            $cookingsByStep[$cooking->getStep()][] = $cooking;
        }

        return $this->render('spicy_match_history/view.html.twig', [
            'spices' => $spices,
            'preparations' => $preparation,
            'cookingsByStep' => $cookingsByStep,
        ]);
    }

    #[Route('/edit/{id}', name: 'edit_spicy_match_history', methods: ['GET'])]
    public function edit(SpicyMatchHistory $spicyMatchHistory, Request $request,EntityManagerInterface $entityManager)
    {
        $spiceId = (int) $request->query->get('spiceId');
        $templates = ""; 
        
        // Be sure this is in $spicyMatchHistory
        $arraySpices = explode(',', $spicyMatchHistory->getSpicyMatchId()->getSpicesIds());

        if ($spiceId && in_array($spiceId, $arraySpices)) {

            $cookingTipId = (int) $request->query->get('cookingId');
            $preparationTipId = (int) $request->query->get('preparationTipId');

            if ($cookingTipId) {

                $arrayCookingTips = explode(',', $spicyMatchHistory->getCookingTipsIds());
                //dump($arrayCookingTips);
                //dump(in_array($cookingTipId, $arrayCookingTips));

                if (in_array($cookingTipId, $arrayCookingTips)) {
                    $cookings = $this->cookingTipsRepository->findBy(['spice' => $spiceId]);
                    // Remove from spicyMatchHistory
                    unset($arrayCookingTips[array_search($cookingTipId, $arrayCookingTips)]);
                } else {
                    // Check if cooking IN Spice
                    $cookings = $this->cookingTipsRepository->findBy(['id' => $cookingTipId]);

                    if(!in_array($cookings[0]->getSpice()->getId(), $arraySpices)){
                        dd('bizarre ?');
                    }
                    
                    // Add to spicyMatchHistory
                    if (empty($arrayCookingTips[0])) {
                        $arrayCookingTips = [$cookingTipId];

                    } else {
                        $arrayCookingTips[] = $cookingTipId;
                    }
                }
                
                $spicyMatchHistory->setCookingTipsIds(implode(",", $arrayCookingTips))
                    ->setUpdatedAt(new \DateTime());
                
                
                /** @var CookingTips $cooking */
                foreach ($cookings as $cooking) {
                    $templates .= $this->render('components/_card_spicy_tips.html.twig', [
                        "cookingTip" => $cooking
                        ])->getContent();
                    }

                } elseif ($preparationTipId) {
                    
                }else{
                    dd('ça dégage');
                    return false;
                }
                
                $entityManager->persist($spicyMatchHistory);
                $entityManager->flush();
                
            return $this->json(
                $templates
            );
        }

        dd('alreardy ?');
        return false;
        
    }
}
