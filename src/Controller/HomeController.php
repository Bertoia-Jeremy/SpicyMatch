<?php

namespace App\Controller;

use App\Repository\SpicesRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $spicesAll = $this->spicesRepository->findAll();

        $spices = $paginator->paginate(
            $spicesAll,
            $request->query->getInt('page', 1),
            8
        );
        return $this->render('home/index.html.twig', [
            'spices' => $spices,
        ]);
    }
}
