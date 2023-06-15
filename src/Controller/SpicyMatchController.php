<?php

namespace App\Controller;

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
     * @Route("/matcher/", name="view_spicy_match")
     */
    public function matcherView(Request $request)
    {
        $token = $request->request->get('_token');

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
        return $this->json(['oki Doki' => 'cbon']);
    }
}
