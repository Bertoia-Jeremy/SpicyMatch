<?php

namespace App\Controller;

use App\Repository\SpicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
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
     * @Route("/", name="home")
     */
    public function index(): Response
    {
        $spices = $this->spicesRepository->findAll();
        return $this->render('home/index.html.twig', [
            'spices' => $spices,
        ]);
    }
}
