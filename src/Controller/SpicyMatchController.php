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
        //Récupération des IDs envoyés par ajax
        $spicesId = json_decode($request->getContent(), true);

        if(count($spicesId) === 0){
            $spicesOrderedByMatch = $this->spicesRepository->findAll();

        }else{
            //Récupération de tous les composés aromatiques
            $allAromaticsCompoundsIds = $this->getAllAromaticsCompounds($spicesId);

            //Filtre et selection des composés aromatiques en commun
            $communAromaticsCompoundsIds = $this->getAromaticsCompoundsInCommon($allAromaticsCompoundsIds, count($spicesId));

            if(!$communAromaticsCompoundsIds){
                // TODO gérer l'exception où l'on ne trouve rien
                return $this->json([]);
            }

            //Récupération de toutes les épices possédant ces composés aromatiques
            $spicesWithCommonAromaticsCompounds = $this->spicesRepository->getByAromaticsCompounds($communAromaticsCompoundsIds);


            //Tri par nombre de match
            $spicesOrderedByMatch = $this->orderSpiceByMatch($spicesWithCommonAromaticsCompounds);
        }

        //Création des templates cards
        $template = $this->render('spicy_match/cards_spices_matched.html.twig', [
            "spices" => $spicesOrderedByMatch,
            "spicesChecked" => $spicesId
        ])->getContent();

        /* $token = $request->request->get('_token');

         if (is_string($token) && $this->isCsrfTokenValid('matcher', $token)) {
             // $this->userRepository->remove($user, true);
             return $this->json(['success' => true]);
         }
         /*   foreach ()
            $spice = $this->spicesRepository->findBy(['id']);
            $jsonData = array();
            $idx = 0;
            foreach($students as $student) {
                $temp = array(
                    'name' => $student->getName(),
                    'address' => $student->getAddress(),
                );
                $jsonData[$idx++] = $temp;
            }
         */
        return $this->json($template);
    }

    /**
     * @throws \Exception
     */
    private function getAllAromaticsCompounds(array $spicesId): array
    {
        $allAromaticsCompoundsIds = [];

        foreach ($spicesId as $keySpice => $id){
            /**
             * @var $spice Spices
             */
            $spice = $this->spicesRepository->findOneBy(['id' => (int) $id]);

            if($spice){
                $iteratorAromaticCompound = $spice->getAromaticsCompounds()->getIterator();

                foreach ($iteratorAromaticCompound as $aromaticCompound) {
                    $aromaticCompoundId = $aromaticCompound->getId();

                    if(!array_key_exists($aromaticCompoundId, $allAromaticsCompoundsIds)){
                        $allAromaticsCompoundsIds[$aromaticCompoundId] = 1;
                    }else{
                        $allAromaticsCompoundsIds[$aromaticCompoundId]++;
                    }
                }
            }
        }

        return $allAromaticsCompoundsIds;
    }

    private function getAromaticsCompoundsInCommon(array $allAromaticsCompoundsIds, int $numberSpices): array
    {
        $communAromaticsCompoundsIds = [];

        foreach ($allAromaticsCompoundsIds as $id => $numberMatch){
            if ($numberMatch === $numberSpices){
                $communAromaticsCompoundsIds[] = $id;
            }
        }

        return $communAromaticsCompoundsIds;
    }

    private function orderSpiceByMatch(array $spicesIds): array
    {
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

        foreach ($spicesIds as $spice){
            $spiceOrdered[] = $this->spicesRepository->findOneBy(['id' => $spice['spices_id']]);
        }

        return $spiceOrdered;
    }

    private function createCardSpiceView(Spices $spice, bool $checked)
    {
        dd($template);
    }
}
