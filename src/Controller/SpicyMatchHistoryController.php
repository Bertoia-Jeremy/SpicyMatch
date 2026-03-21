<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\Spices;
use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use App\Message\FavoriteToggledEvent;
use App\Repository\CookingTipsRepository;
use App\Repository\PreparationTipsRepository;
use App\Repository\SpicyMatchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/spicymatch/history')]
class SpicyMatchHistoryController extends AbstractController
{
    public function __construct(
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly PreparationTipsRepository $preparationTipsRepository,
        private readonly CookingTipsRepository $cookingTipsRepository,
        private readonly MessageBusInterface $bus,
    ) {
    }

    #[Route('/', name: 'index_spicy_match_history', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        return $this->render('spicy_match_history/index.html.twig', [
            'spicymatch_histories' => $this->historyRepository->findByUser($user),
            'favoriteCount' => $this->historyRepository->countFavoritesByUser($user),
        ]);
    }

    #[Route('/favorites', name: 'favorites_spicy_match_history', methods: ['GET'])]
    public function favorites(): Response
    {
        /** @var Users $user */
        $user = $this->getUser();

        return $this->render('spicy_match_history/favorites.html.twig', [
            'spicymatch_histories' => $this->historyRepository->findFavoritesByUser($user),
        ]);
    }

    #[Route('/view/{id}', name: 'view_spicy_match_history', methods: ['GET'])]
    public function view(SpicyMatchHistory $spicyMatchHistory): Response
    {
        /** @var Users $currentUser */
        $currentUser = $this->getUser();
        if ($spicyMatchHistory->getSpicyMatch()->getUser() !== $currentUser) {
            throw $this->createAccessDeniedException();
        }

        if ($spicyMatchHistory->getPreparationTips()->isEmpty()) {
            return $this->redirectToRoute('view_spicy_match', [
                'id' => $spicyMatchHistory->getSpicyMatch()
                    ->getId(),
            ]);
        }

        $cookingsByStep = [
            0 => [],
            1 => [],
            2 => [],
            3 => [],
            4 => [],
        ];
        foreach ($spicyMatchHistory->getCookingTips() as $cooking) {
            $step = $cooking->getStep() ?? 0;
            $cookingsByStep[$step][] = $cooking;
        }

        // Calcul des composés aromatiques partagés entre toutes les épices du mélange
        $sharedCompounds = null;
        foreach ($spicyMatchHistory->getSpicyMatch()->getSpices() as $spice) {
            $compounds = $spice->getAromaticsCompounds()
                ->toArray();
            if ($sharedCompounds === null) {
                $sharedCompounds = $compounds;
            } else {
                $sharedCompounds = array_uintersect(
                    $sharedCompounds,
                    $compounds,
                    static fn ($a, $b) => $a->getId() <=> $b->getId()
                );
            }
        }

        return $this->render('spicy_match_history/view.html.twig', [
            'preparations' => $spicyMatchHistory->getPreparationTips(),
            'cookingsByStep' => $cookingsByStep,
            'sharedCompounds' => array_values($sharedCompounds ?? []),
            'spicyMatch' => $spicyMatchHistory->getSpicyMatch(),
        ]);
    }

    #[Route('/edit/{id}', name: 'edit_spicy_match_history', methods: ['GET'])]
    public function edit(
        SpicyMatchHistory $spicyMatchHistory,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var Users $currentUser */
        $currentUser = $this->getUser();
        if ($spicyMatchHistory->getSpicyMatch()->getUser() !== $currentUser) {
            throw $this->createAccessDeniedException();
        }

        $spiceId = (int) $request->query->get('spiceId');

        // Verify the requested spice belongs to this match
        $matchSpiceIds = $spicyMatchHistory->getSpicyMatch()
            ->getSpices()
            ->map(fn (Spices $s) => $s->getId())
            ->toArray();

        if (! in_array($spiceId, $matchSpiceIds, true)) {
            return $this->json($this->renderView('Exception/Error.html.twig', [
                'codeError' => '105',
            ]));
        }

        $cookingTipId = (int) $request->query->get('cookingId');
        $preparationTipId = (int) $request->query->get('preparationId');

        if ($cookingTipId) {
            return $this->handleCookingTip($spicyMatchHistory, $spiceId, $cookingTipId, $entityManager);
        }

        if ($preparationTipId) {
            return $this->handlePreparationTip($spicyMatchHistory, $spiceId, $preparationTipId, $entityManager);
        }

        return $this->json($this->renderView('Exception/Error.html.twig', [
            'codeError' => '173',
        ]));
    }

    #[Route('/{id}/rename', name: 'rename_spicy_match_history', methods: ['POST'])]
    public function rename(
        SpicyMatchHistory $spicyMatchHistory,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        /** @var Users $currentUser */
        $currentUser = $this->getUser();
        if ($spicyMatchHistory->getSpicyMatch()->getUser() !== $currentUser) {
            throw $this->createAccessDeniedException();
        }

        $data = json_decode($request->getContent(), true);

        if (! $this->isCsrfTokenValid('history_action_' . $spicyMatchHistory->getId(), $data['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], 403);
        }

        $title = trim((string) ($data['title'] ?? ''));
        $spicyMatchHistory->setTitle($title !== '' ? $title : null);
        $spicyMatchHistory->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        return $this->json([
            'title' => $spicyMatchHistory->getTitle(),
        ]);
    }

    #[Route('/{id}/favorite/toggle', name: 'toggle_favorite_spicy_match_history', methods: ['POST'])]
    public function toggleFavorite(
        SpicyMatchHistory $spicyMatchHistory,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        /** @var Users $currentUser */
        $currentUser = $this->getUser();
        if ($spicyMatchHistory->getSpicyMatch()->getUser() !== $currentUser) {
            throw $this->createAccessDeniedException();
        }

        $token = $request->headers->get('X-CSRF-Token', '');
        if (! $this->isCsrfTokenValid('history_action_' . $spicyMatchHistory->getId(), $token)) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], 403);
        }

        $spicyMatchHistory->setFavorite(! $spicyMatchHistory->isFavorite());
        $spicyMatchHistory->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        // Dispatch async gamification event uniquement quand on ajoute aux favoris
        if ($spicyMatchHistory->isFavorite()) {
            $this->bus->dispatch(new FavoriteToggledEvent($currentUser->getId()));
        }

        return $this->json([
            'favorite' => $spicyMatchHistory->isFavorite(),
        ]);
    }

    private function handleCookingTip(
        SpicyMatchHistory $history,
        int $spiceId,
        int $cookingTipId,
        EntityManagerInterface $em,
    ): Response {
        // Find the currently selected cooking tip for this spice (at most one per spice)
        $existing = null;
        foreach ($history->getCookingTips()->toArray() as $tip) {
            /** @var CookingTips $tip */
            if ($tip->getSpice()?->getId() === $spiceId) {
                $existing = $tip;
                break;
            }
        }

        if ($existing?->getId() === $cookingTipId) {
            // Toggle OFF: remove and return all tips for this spice
            $history->removeCookingTip($existing);
            $cookings = $this->cookingTipsRepository->findBy([
                'spice' => $spiceId,
            ]);
        } else {
            // Toggle ON: replace existing (if any) with the new tip
            if ($existing) {
                $history->removeCookingTip($existing);
            }
            $cookingTip = $this->cookingTipsRepository->find($cookingTipId);
            if (! $cookingTip instanceof CookingTips || $cookingTip->getSpice()?->getId() !== $spiceId) {
                return $this->json($this->renderView('Exception/Error.html.twig', [
                    'codeError' => '105',
                ]));
            }
            $history->addCookingTip($cookingTip);
            $cookings = [$cookingTip];
        }

        $history->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $html = '';
        foreach ($cookings as $cooking) {
            $html .= $this->renderView('components/_card_spicy_tips.html.twig', [
                'cookingTip' => $cooking,
            ]);
        }

        return $this->json($html);
    }

    private function handlePreparationTip(
        SpicyMatchHistory $history,
        int $spiceId,
        int $preparationTipId,
        EntityManagerInterface $em,
    ): Response {
        // Find the currently selected preparation tip for this spice (at most one per spice)
        $existing = null;
        foreach ($history->getPreparationTips()->toArray() as $tip) {
            /** @var PreparationTips $tip */
            if ($tip->getSpice()?->getId() === $spiceId) {
                $existing = $tip;
                break;
            }
        }

        if ($existing?->getId() === $preparationTipId) {
            // Toggle OFF: remove and return all tips for this spice
            $history->removePreparationTip($existing);
            $preparations = $this->preparationTipsRepository->findBy([
                'spice' => $spiceId,
            ]);
        } else {
            // Toggle ON: replace existing (if any) with the new tip
            if ($existing) {
                $history->removePreparationTip($existing);
            }
            $preparationTip = $this->preparationTipsRepository->find($preparationTipId);
            if (! $preparationTip instanceof PreparationTips || $preparationTip->getSpice()?->getId() !== $spiceId) {
                return $this->json($this->renderView('Exception/Error.html.twig', [
                    'codeError' => '146',
                ]));
            }
            $history->addPreparationTip($preparationTip);
            $preparations = [$preparationTip];
        }

        $history->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $html = '';
        foreach ($preparations as $preparation) {
            $html .= $this->renderView('components/_card_spicy_tips.html.twig', [
                'preparationTip' => $preparation,
            ]);
        }

        return $this->json($html);
    }
}
