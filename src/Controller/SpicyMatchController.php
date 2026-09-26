<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SpicyMatch;
use App\Entity\Users;
use App\Factory\SpicyMatchHistoryFactory;
use App\Message\MatchSavedEvent;
use App\Repository\SpiceDuoRepository;
use App\Repository\SpicesRepository;
use App\Service\Match\SpiceDuoMapBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/spicymatch', defaults: [
    '_locale' => 'fr',
])]
class SpicyMatchController extends AbstractController
{
    #[Route('/', name: 'index_spicy_match')]
    public function index(): Response
    {
        return $this->render('spicy_match/index.html.twig');
    }

    #[Route('/view/{id<\d+>}', name: 'view_spicy_match')]
    #[IsGranted('ROLE_USER')]
    public function view(
        SpicyMatch $spicyMatch,
        SpicyMatchHistoryFactory $spicyMatchHistoryFactory,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
        SpicesRepository $spicesRepository,
        SpiceDuoRepository $spiceDuoRepository,
        SpiceDuoMapBuilder $duoMapBuilder,
        Request $request,
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

        $bus->dispatch(new MatchSavedEvent($spicyMatchHistory->getId(), $currentUser->getId()));

        $spiceIds = array_values(array_filter($spicyMatch->getSpices()->map(static fn ($s) => $s->getId())->toArray()));
        $loaded = [];
        foreach ($spicesRepository->findForLab($spiceIds, $request->getLocale()) as $spice) {
            $loaded[$spice->getId()] = $spice;
        }
        $spices = array_values(array_filter(array_map(static fn (int $id) => $loaded[$id] ?? null, $spiceIds)));

        $duoRows = $spiceDuoRepository->findBySpiceIds($spiceIds, $request->getLocale());

        return $this->render('spicy_match/view.html.twig', [
            'spicyMatchHistory' => $spicyMatchHistory,
            'spicyMatch' => $spicyMatch,
            'spices' => $spices,
            'duoMap' => $duoMapBuilder->build($duoRows),
            'duoTips' => $duoMapBuilder->tooltips($duoRows),
        ]);
    }
}
