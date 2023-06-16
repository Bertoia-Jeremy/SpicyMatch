<?php

namespace App\Controller;

use App\Entity\Spices;
use App\Repository\AromaticCompoundRepository;
use App\Repository\SpicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/spicymatch")
 */
class SpicyMatchController extends AbstractController
{
    /**
     * @var SpicesRepository
     */
    private $spicesRepository;
    public function __construct(
        SpicesRepository $spicesRepository
    )
    {
        $this->spicesRepository = $spicesRepository;
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
     */
    public function matcherView(Request $request): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $spicesId = json_decode($request->getContent(), true);
        $allAromaticsCompoundsIds = $this->getAllAromaticsCompounds($spicesId);
        $communAromaticsCompoundsIds = $this->getAromaticsCompoundInCommon($allAromaticsCompoundsIds, count($spicesId));

        dd($allAromaticsCompoundsIds, $communAromaticsCompoundsIds);
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
        return $this->json($request);
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

    private function getAromaticsCompoundInCommon(array $allAromaticsCompoundsIds, int $numberSpices): array
    {
        $communAromaticsCompoundsIds = [];

        foreach ($allAromaticsCompoundsIds as $id => $numberMatch){
            if ($numberMatch === $numberSpices){
                $communAromaticsCompoundsIds[] = $id;
            }
        }

        return $communAromaticsCompoundsIds;
    }
}
