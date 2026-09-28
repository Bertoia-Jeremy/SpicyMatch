<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\Spices;
use App\Entity\SpicyMatchHistory;
use App\Entity\Users;
use App\Enum\CookingMoment;
use App\Enum\HistoryTipKind;
use App\Exception\Match\InvalidMortarException;
use App\Message\FavoriteToggledEvent;
use App\Message\MatchSavedEvent;
use App\Repository\CookingTipsRepository;
use App\Repository\PreparationTipsRepository;
use App\Repository\SpiceDuoRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyMatchHistoryRepository;
use App\Security\Voter\SpicyMatchHistoryVoter;
use App\Service\Match\CookingTimelineBuilder;
use App\Service\Match\MatrixComparator;
use App\Service\Match\SpiceDuoMapBuilder;
use App\ValueObject\Match\MortarIds;
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
        MatrixComparator $matrixComparator,
        CookingTimelineBuilder $timelineBuilder,
        Request $request,
    ): Response {
        if (! $spicyMatchHistory->isSealed()) {
            return $this->redirectToRoute('finalize_spicy_match_history', [
                'id' => $spicyMatchHistory->getId(),
            ]);
        }

        $cookingsByStep = array_fill_keys(array_column(CookingMoment::cases(), 'value'), []);
        foreach ($spicyMatchHistory->getCookingTips() as $cooking) {
            $cookingsByStep[($cooking->getMoment() ?? CookingMoment::PRE)->value][] = $cooking;
        }

        $prepTipIds = [];
        foreach ($spicyMatchHistory->getPreparationTips() as $prepTip) {
            $prepTipIds[] = (int) $prepTip->getId();
        }
        $cookTipIds = [];
        foreach ($spicyMatchHistory->getCookingTips() as $cookTip) {
            $cookTipIds[] = (int) $cookTip->getId();
        }
        $duoByPrep = [];
        foreach ($this->duoRepository->findByTipIds($prepTipIds, $cookTipIds, $request->getLocale()) as $row) {
            $duoByPrep[$row['prepId']] ??= $row;
        }

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
                    static fn ($a, $b): int => $a->getId() <=> $b->getId()
                );
            }
        }

        $spicyMatch = $spicyMatchHistory->getSpicyMatch();
        $culinaryContext = $spicyMatch->getCulinaryContext();
        $mortarSpiceIds = $spicyMatch->getSpices()
            ->map(static fn (Spices $s): ?int => $s->getId())
            ->filter(static fn (?int $id): bool => $id !== null)
            ->toArray();

        $matrixGrid = [];
        $boundedIds = array_slice(array_values($mortarSpiceIds), 0, 10);
        if ($boundedIds !== []) {
            try {
                $matrixRankings = $matrixComparator->compare(
                    new MortarIds($boundedIds),
                    $culinaryContext,
                    limit: 5,
                    locale: $request->getLocale(),
                );
                $matrixGrid = $matrixComparator->buildGrid($matrixRankings);
            } catch (InvalidMortarException) {
                $matrixGrid = [];
            }
        }

        $mortarCompounds = [];
        $seenCompoundIds = [];
        foreach ($spicyMatch->getSpices() as $spice) {
            foreach ($spice->getAromaticsCompounds() as $compound) {
                $cid = $compound->getId();
                if ($cid === null || isset($seenCompoundIds[$cid])) {
                    continue;
                }
                $seenCompoundIds[$cid] = true;
                $mortarCompounds[] = $compound;
            }
        }
        $cookingTimeline = $timelineBuilder->build($mortarCompounds, $culinaryContext);

        return $this->render('spicy_match_history/view.html.twig', [
            'spicyMatchHistory' => $spicyMatchHistory,
            'preparations' => $spicyMatchHistory->getPreparationTips(),
            'cookingsByStep' => $cookingsByStep,
            'duoByPrep' => $duoByPrep,
            'sharedCompounds' => array_values($sharedCompounds ?? []),
            'spicyMatch' => $spicyMatch,
            'culinaryContext' => $culinaryContext,
            'matrixGrid' => $matrixGrid,
            'cookingTimeline' => $cookingTimeline,
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
        $data = json_decode($request->getContent(), true);

        if (! $this->isCsrfTokenValid('history_action_' . $spicyMatchHistory->getId(), $data['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], 403);
        }

        $title = trim((string) ($data['title'] ?? ''));
        $spicyMatchHistory->setTitle($title !== '' ? $title : null);
        $spicyMatchHistory->setUpdatedAt($this->clock->now());
        $entityManager->flush();

        return $this->json([
            'title' => $spicyMatchHistory->getTitle(),
        ]);
    }

    #[Route('/{id}/favorite/toggle', name: 'toggle_favorite_spicy_match_history', methods: ['POST'])]
    #[IsGranted(SpicyMatchHistoryVoter::OWNER, 'spicyMatchHistory')]
    public function toggleFavorite(
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

        $spicyMatchHistory->setFavorite(! $spicyMatchHistory->isFavorite());
        $spicyMatchHistory->setUpdatedAt($this->clock->now());
        $entityManager->flush();

        if ($spicyMatchHistory->isFavorite()) {
            $this->bus->dispatch(new FavoriteToggledEvent($currentUser->getId()));
        }

        return $this->json([
            'favorite' => $spicyMatchHistory->isFavorite(),
        ]);
    }
}
