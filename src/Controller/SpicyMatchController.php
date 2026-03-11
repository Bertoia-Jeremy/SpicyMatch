<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SpicyMatch;
use App\Entity\Users;
use App\Factory\SpicyMatchHistoryFactory;
use App\Message\MatchSavedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/spicymatch')]
#[IsGranted('ROLE_USER')]
class SpicyMatchController extends AbstractController
{
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
        MessageBusInterface $bus,
    ): Response {
        /** @var Users $currentUser */
        $currentUser = $this->getUser();

        if ($spicyMatch->getUser() !== $currentUser) {
            throw $this->createAccessDeniedException();
        }

        if ($spicyMatch->getSpices()->isEmpty()) {
            return $this->redirectToRoute('index_spicy_match');
        }

        $spicyMatchHistory = $spicyMatchHistoryFactory->create($spicyMatch);
        $entityManager->persist($spicyMatchHistory);
        $entityManager->flush();

        // Dispatch async gamification event
        $bus->dispatch(new MatchSavedEvent($spicyMatchHistory->getId(), $currentUser->getId()));

        return $this->render('spicy_match/view.html.twig', [
            'spicyMatchHistory' => $spicyMatchHistory,
            'spicyMatch' => $spicyMatch,
            'spices' => $spicyMatch->getSpices(),
        ]);
    }
}
