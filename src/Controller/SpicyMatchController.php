<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SpicyMatch;
use App\Factory\SpicyMatchHistoryFactory;
use App\Form\SpicyMatchHistoryType;
use App\Repository\SpicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/spicymatch')]
class SpicyMatchController extends AbstractController
{
    public function __construct(
        private SpicesRepository $spicesRepository
    ) {
    }

    #[Route('/', name: 'index_spicy_match')]
    public function index(): Response
    {
        return $this->render('spicy_match/index.html.twig');
    }

    #[Route('/view/{id<\d+>}', name: 'view_spicy_match')]
    public function view(
        SpicyMatch $spicyMatch, 
        SpicyMatchHistoryFactory $spicyMatchHistoryFactory,
        EntityManagerInterface $entityManager,
        ): Response
    {
        $spices = $this->spicesRepository->findAllByStringIds($spicyMatch->getSpicesIds());
        $spicyMatchHistory = $spicyMatchHistoryFactory->create($spicyMatch);
        
        $entityManager->persist($spicyMatchHistory);
        $entityManager->flush();

        return $this->render('spicy_match/view.html.twig',
            [
                'spicyMatchHistory' => $spicyMatchHistory,
                'spicyMatch' => $spicyMatch,
                'spices' => $spices,
            ]
        );
    }
}
