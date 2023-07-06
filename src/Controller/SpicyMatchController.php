<?php

namespace App\Controller;

use App\Entity\Spices;
use App\Repository\AromaticCompoundRepository;
use App\Repository\SpicesRepository;
use Doctrine\DBAL\Driver\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/spicymatch")
 */
class SpicyMatchController extends AbstractController
{

    private $spicesRepository;
    private $aromaticCompoundRepository;

    public function __construct(
        SpicesRepository $spicesRepository,
        AromaticCompoundRepository  $aromaticCompoundRepository
    )
    {
        $this->spicesRepository = $spicesRepository;
        $this->aromaticCompoundRepository = $aromaticCompoundRepository;
    }

    /**
     * @Route("/", name="index_spicy_match")
     */
    public function index(Request $request): Response
    {
        /*
         * Récupérer les ids, faire un foreach récupérer les composés, récupérer à la fin toutes les épices par rapport au composé
         */
        //Prendre exemple sur adminUserController dans le projet de la billeterie
        //admin/user/index.html.twig
        // Pour la macro : https://stackoverflow.com/questions/23315104/putting-twig-generates-html-into-a-js-variable
        if ($request->isXmlHttpRequest()) {

        } else {
            $spices = $this->spicesRepository->findAll();

            return $this->render('spicy_match/index.html.twig', [
                'spices' => $spices,
            ]);
        }
    }

    /**
     * @Route("/matcher/", name="view_spicy_match", methods={"POST"})
     * @throws \Exception
     * @throws Exception
     */
    public function matcherView(Request $request): \Symfony\Component\HttpFoundation\JsonResponse
    {
        //TODO => sécuriser l'appel ajax
        //Récupération des IDs envoyés par ajax
        $spicesId = json_decode($request->getContent(), true);

        if(count($spicesId) === 0){
            $spicesEntity = $this->spicesRepository->findAll();

        }else{
            //Récupération de tous les composés aromatiques
            $allAromaticsCompoundsIds = $this->getAllAromaticsCompounds($spicesId);

            //Faire le tri en 4 parties
            // Principaux sur principaux
            // Principaux sur secondaires (Possibilité de switch avec Secondaires sur principaux)
            // Secondaires sur principaux
            // Secondaires sur secondaires

            //Filtre et selection des composés aromatiques en commun
            $communAromaticsCompoundsIds = $this->getAromaticsCompoundsInCommon($allAromaticsCompoundsIds, count($spicesId));

            if(!$communAromaticsCompoundsIds){
                return $this->json([]);
            }

            //Récupération de toutes les épices possédant ces composés aromatiques
            $mainMain = $this->spicesRepository->getByMainAromaticsCompounds($communAromaticsCompoundsIds["main"]);
            $mainSecondary = $this->spicesRepository->getBySecondaryAromaticsCompounds($communAromaticsCompoundsIds["main"]);
            $secondaryMain = $this->spicesRepository->getByMainAromaticsCompounds($communAromaticsCompoundsIds["secondary"]);
            $secondarySecondary = $this->spicesRepository->getBySecondaryAromaticsCompounds($communAromaticsCompoundsIds["secondary"]);


            //Tri par nombre de match
            $spicesOrderedByMatch = $this->orderSpiceByMatch([
                $mainMain, $secondaryMain, $mainSecondary, $secondarySecondary
            ]);

            $spicesEntity = $this->getSpicesEntity($spicesOrderedByMatch);
        }

        //Création des templates cards
        $template = $this->render('spicy_match/cards_spices_matched.html.twig', [
            "spices" => $spicesEntity,
            "spicesChecked" => $spicesId
        ])->getContent();

        /* $token = $request->request->get('_token');

         if (is_string($token) && $this->isCsrfTokenValid('matcher', $token)) {
             // $this->userRepository->remove($user, true);
             return $this->json(['success' => true]);
         }
        */

        return $this->json($template);
    }

    /**
     * @throws \Exception
     */
    private function getAllAromaticsCompounds(array $spicesId): array
    {
        $mainAromaticsCompoundsIds = [];
        $secondaryAromaticsCompoundsIds = [];

        foreach ($spicesId as $id){
            /**
             * @var $spice Spices
             */
            $spice = $this->spicesRepository->findOneBy(['id' => (int) $id]);

            if($spice){
                $arrayMainAromaticCompound = $spice->getAromaticsCompounds()->getIterator();
                $mainAromaticsCompoundsIds = $this->getAromaticsCompoundsFromIteratorSpice($arrayMainAromaticCompound, $mainAromaticsCompoundsIds);

                $arraySecondaryAromaticCoumpound = $spice->getSecondaryAromaticsCompounds()->getIterator();
                $secondaryAromaticsCompoundsIds = $this->getAromaticsCompoundsFromIteratorSpice($arraySecondaryAromaticCoumpound, $secondaryAromaticsCompoundsIds);
            }
        }

        return [
            "main" => $mainAromaticsCompoundsIds,
            "secondary" => $secondaryAromaticsCompoundsIds
        ];
    }

    private function getAromaticsCompoundsInCommon(array $allAromaticsCompoundsIds, int $numberSpices): array
    {
        $mainCompounds = $allAromaticsCompoundsIds["main"];
        $secondaryCompounds = $allAromaticsCompoundsIds["secondary"];
        $mainCommon = $secondaryCommon = [];

        foreach ($mainCompounds as $id => $numberMatchMain){
            $numberMatch = $numberMatchMain + $secondaryCompounds[$id];

            if ($numberMatch === $numberSpices){
                $mainCommon[] = $id;
            }
        }

        foreach ($secondaryCompounds as $id => $numberMatchSecondary){
            $numberMatch = $numberMatchSecondary + $mainCompounds[$id];

            if (($numberMatch === $numberSpices) && !isset($mainCommon[$id])){
                $secondaryCommon[] = $id;
            }
        }

        return [
            "main" => $mainCommon,
            "secondary" => $secondaryCommon
        ];
    }

    private function orderSpiceByMatch(array $spicesIds): array
    {
        // TODO Finir ce morceau et tester le nouveau système de match
        $order = $spiceOrdered = [];
        // TODO Trouver un truc pour ordonner par match

       /* foreach ($spicesIds as $spice){
            $id = $spice['spices_id'];
dd($id);
            if(isset($order[$id])) {
                $order[$id]++;
            }else{
                $order[$id] = 1;
            }
        }

        sort($order);*/


    }

    private function getAromaticsCompoundsFromIteratorSpice($iteratorAromaticCompound, $allAromaticsCompoundsIds): array
    {
        foreach ($iteratorAromaticCompound as $aromaticCompound) {
            $aromaticCompoundId = $aromaticCompound->getId();

            if(!array_key_exists($aromaticCompoundId, $allAromaticsCompoundsIds)){
                $allAromaticsCompoundsIds[$aromaticCompoundId] = 1;
            }else{
                $allAromaticsCompoundsIds[$aromaticCompoundId]++;
            }
        }

        return $allAromaticsCompoundsIds;
    }

    private function getSpicesEntity(array $spicesOrderedByMatch): array
    {
        $spicesEntity = [];

        foreach ($spicesOrderedByMatch as $spice){
            $spicesEntity[] = $this->spicesRepository->findOneBy(['id' => $spice['spices_id']]);
        }

        return $spicesEntity;
    }
}
