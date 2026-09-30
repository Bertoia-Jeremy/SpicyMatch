<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\Spices;
use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use App\Enum\HistoryTipKind;
use App\Message\FavoriteToggledEvent;
use App\Message\MatchSavedEvent;
use App\Repository\CookingTipsRepository;
use App\Repository\PreparationTipsRepository;
use App\Repository\SpiceDuoRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyMatchHistoryRepository;
use App\Security\Voter\SpicyMatchHistoryVoter;
use App\Service\Match\SpiceDuoMapBuilder;
use App\Service\Recipe\RecipeViewFactory;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/{_locale}/spicymatch/history', defaults: [
    '_locale' => 'fr',
])]
class SpicyMatchHistoryController extends AbstractController
{
    public function __construct(
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly PreparationTipsRepository $preparationTipsRepository,
        private readonly CookingTipsRepository $cookingTipsRepository,
        private readonly SpiceDuoRepository $duoRepository,
        private readonly MessageBusInterface $bus,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/', name: 'index_spicy_match_history', methods: ['GET'])]
    public function index(#[CurrentUser] Users $user): Response
    {
        return $this->render('spicy_match_history/index.html.twig', [
            'spicymatch_histories' => $this->historyRepository->findByUser($user),
            'favoriteCount' => $this->historyRepository->countFavoritesByUser($user),
        ]);
    }

    #[Route('/favorites', name: 'favorites_spicy_match_history', methods: ['GET'])]
    public function favorites(#[CurrentUser] Users $user): Response
    {
        return $this->render('spicy_match_history/favorites.html.twig', [
            'spicymatch_histories' => $this->historyRepository->findFavoritesByUser($user),
        ]);
    }

    #[Route('/{id<\d+>}/finalize', name: 'finalize_spicy_match_history', methods: ['GET'])]
    #[IsGranted(SpicyMatchHistoryVoter::OWNER, 'spicyMatchHistory')]
    public function finalize(
        SpicyMatchHistory $spicyMatchHistory,
        SpicesRepository $spicesRepository,
        SpiceDuoMapBuilder $duoMapBuilder,
        Request $request,
    ): Response {
        $spicyMatch = $spicyMatchHistory->getSpicyMatch();
        if ($spicyMatch === null || $spicyMatch->getSpices()->isEmpty()) {
            return $this->redirectToRoute('index_spicy_match');
        }

        $spiceIds = array_values(array_filter($spicyMatch->getSpices()->map(static fn (Spices $s): ?int => $s->getId())->toArray()));
        $loaded = [];
        foreach ($spicesRepository->findForLab($spiceIds, $request->getLocale()) as $spice) {
            $loaded[$spice->getId()] = $spice;
        }
        $spices = array_values(array_filter(array_map(static fn (int $id): ?Spices => $loaded[$id] ?? null, $spiceIds)));

        $selections = [];
        foreach ($spiceIds as $spiceId) {
            $selections[$spiceId] = [
                HistoryTipKind::COOKING->value => null,
                HistoryTipKind::PREPARATION->value => null,
            ];
        }
        foreach ($spicyMatchHistory->getCookingTips() as $tip) {
            $spiceId = $tip->getSpice()?->getId();
            if ($spiceId !== null && isset($selections[$spiceId])) {
                $selections[$spiceId][HistoryTipKind::COOKING->value] = $tip->getId();
            }
        }
        foreach ($spicyMatchHistory->getPreparationTips() as $tip) {
            $spiceId = $tip->getSpice()?->getId();
            if ($spiceId !== null && isset($selections[$spiceId])) {
                $selections[$spiceId][HistoryTipKind::PREPARATION->value] = $tip->getId();
            }
        }

        $duoRows = $this->duoRepository->findBySpiceIds($spiceIds, $request->getLocale());

        return $this->render('spicy_match_history/finalize.html.twig', [
            'spicyMatchHistory' => $spicyMatchHistory,
            'spicyMatch' => $spicyMatch,
            'spices' => $spices,
            'selections' => $selections,
            'duoMap' => $duoMapBuilder->build($duoRows),
            'duoTips' => $duoMapBuilder->tooltips($duoRows),
        ]);
    }

    #[Route('/view/{id}', name: 'view_spicy_match_history', methods: ['GET'])]
    #[IsGranted(SpicyMatchHistoryVoter::OWNER, 'spicyMatchHistory')]
    public function view(
        SpicyMatchHistory $spicyMatchHistory,
        RecipeViewFactory $recipeViewFactory,
        Request $request,
    ): Response {
        if (! $spicyMatchHistory->isSealed()) {
            return $this->redirectToRoute('finalize_spicy_match_history', [
                'id' => $spicyMatchHistory->getId(),
            ]);
        }

        return $this->render('spicy_match_history/view.html.twig', [
            'history' => $spicyMatchHistory,
            'recipe' => $recipeViewFactory->build($spicyMatchHistory, $request->getLocale()),
        ]);
    }

    #[Route('/edit/{id}', name: 'edit_spicy_match_history', methods: ['POST'])]
    #[IsGranted(SpicyMatchHistoryVoter::OWNER, 'spicyMatchHistory')]
    public function edit(
        SpicyMatchHistory $spicyMatchHistory,
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser]
        Users $currentUser,
    ): JsonResponse {
        $token = $request->headers->get('X-CSRF-Token', '');
        if (! $this->isCsrfTokenValid('history_edit_' . $spicyMatchHistory->getId(), $token)) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], 403);
        }

        $kind = $request->request->getEnum('kind', HistoryTipKind::class);
        $spiceId = $request->request->getInt('spiceId');
        $tipId = $request->request->getInt('tipId');

        $spice = $spicyMatchHistory->getSpicyMatch()
            ?->getSpices()
            ->findFirst(static fn (int $key, Spices $s): bool => $s->getId() === $spiceId);

        if ($kind === null || $spice === null) {
            return $this->json([
                'error' => 'Invalid spice or kind',
            ], 400);
        }

        $tip = null;
        if ($tipId > 0) {
            $tip = $kind === HistoryTipKind::COOKING
                ? $this->cookingTipsRepository->find($tipId)
                : $this->preparationTipsRepository->find($tipId);
            if ($tip === null || $tip->getSpice() !== $spice) {
                return $this->json([
                    'error' => 'Unknown tip for this spice',
                ], 404);
            }
        }

        $entityManager->wrapInTransaction(function (EntityManagerInterface $em) use ($spicyMatchHistory, $spice, $kind, $tip, $currentUser): void {
            $em->refresh($spicyMatchHistory, LockMode::PESSIMISTIC_WRITE);

            if ($kind === HistoryTipKind::COOKING) {
                \assert($tip === null || $tip instanceof CookingTips);
                $spicyMatchHistory->chooseCookingTip($spice, $tip);
            } else {
                \assert($tip === null || $tip instanceof PreparationTips);
                $spicyMatchHistory->choosePreparationTip($spice, $tip);
            }

            $now = $this->clock->now();
            $spicyMatchHistory->setUpdatedAt($now);

            if ($spicyMatchHistory->markSealedIfComplete($now)) {
                $this->bus->dispatch(new MatchSavedEvent((int) $spicyMatchHistory->getId(), (int) $currentUser->getId()));
            }
        });

        return $this->json([
            'spiceId' => $spiceId,
            'kind' => $kind->value,
            'tipId' => $tip?->getId(),
        ]);
    }

    #[Route('/{id}/rename', name: 'rename_spicy_match_history', methods: ['POST'])]
    #[IsGranted(SpicyMatchHistoryVoter::OWNER, 'spicyMatchHistory')]
    public function rename(
        SpicyMatchHistory $spicyMatchHistory,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $data = $request->toArray();
        $token = $data['_token'] ?? '';

        if (! \is_string($token) || ! $this->isCsrfTokenValid('history_action_' . $spicyMatchHistory->getId(), $token)) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], 403);
        }

        $raw = $data['title'] ?? '';
        $title = \is_string($raw) ? mb_substr(trim($raw), 0, SpicyMatchHistory::TITLE_MAX_LENGTH) : '';
        $spicyMatchHistory->setTitle($title !== '' ? $title : null);
        $spicyMatchHistory->setUpdatedAt($this->clock->now());
        $entityManager->flush();

        return $this->json([
            'title' => $spicyMatchHistory->getTitle(),
        ]);
    }

    #[Route('/{id}/favorite', name: 'set_favorite_spicy_match_history', methods: ['POST'])]
    #[IsGranted(SpicyMatchHistoryVoter::OWNER, 'spicyMatchHistory')]
    public function setFavorite(
        SpicyMatchHistory $spicyMatchHistory,
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser]
        Users $currentUser,
    ): JsonResponse {
        $token = $request->headers->get('X-CSRF-Token', '');
        if (! $this->isCsrfTokenValid('history_action_' . $spicyMatchHistory->getId(), $token)) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], 403);
        }

        $favorite = $request->toArray()['favorite'] ?? null;
        if (! \is_bool($favorite)) {
            return $this->json([
                'error' => 'Invalid favorite value',
            ], 400);
        }

        $wasFavorite = $spicyMatchHistory->isFavorite();
        if ($wasFavorite !== $favorite) {
            $spicyMatchHistory->setFavorite($favorite);
            $spicyMatchHistory->setUpdatedAt($this->clock->now());
            $entityManager->flush();
        }

        if (! $wasFavorite && $favorite) {
            $this->bus->dispatch(new FavoriteToggledEvent($currentUser->getId()));
        }

        return $this->json([
            'favorite' => $spicyMatchHistory->isFavorite(),
        ]);
    }
}
