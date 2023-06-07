<?php

namespace App\Controller;

use App\Repository\SpicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    public function __construct(SpicesRepository $spicesRepository)
    {
        $this->spicesRepository = $spicesRepository;
    }

    /**
     * @Route("/", name="index_spicy_match")
     */
    public function index(): Response
    {
        $spices = $this->spicesRepository->findAll();
        return $this->render('spicy_match/index.html.twig', [
            'spices' => $spices,
        ]);
    }
}
