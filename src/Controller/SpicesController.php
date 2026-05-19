<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Spices;
use App\Entity\Users;
use App\Message\SpiceReadEvent;
use App\Repository\AromaticGroupsRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpiceViewRepository;
use App\Repository\SpicyTypeRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/epices')]
class SpicesController extends AbstractController
{
    public function __construct(
        private SpicesRepository $spicesRepository,
    ) {
    }

    #[Route('/', name: 'index_spices')]
    public function index(
        Request $request,
        PaginatorInterface $paginator,
        AromaticGroupsRepository $aromaticGroupsRepository,
        SpicyTypeRepository $spicyTypeRepository,
    ): Response {
        $agId = filter_var(
            $request->query->get('aromatic_group'),
            FILTER_VALIDATE_INT,
            FILTER_NULL_ON_FAILURE
        ) ?: null;
        $stId = filter_var($request->query->get('spicy_type'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?: null;
        $search = trim((string) $request->query->get('search', '')) ?: null;

        $query = ($agId !== null || $stId !== null || $search !== null)
            ? $this->spicesRepository->findFiltered($agId, $stId, $search)
            : $this->spicesRepository->findAll();

        $limit = $request->query->getInt('limit', 12);

        $spices = $paginator->paginate($query, $request->query->getInt('page', 1), $limit);

        return $this->render('spices/index.html.twig', [
            'spices' => $spices,
            'aromaticGroups' => $aromaticGroupsRepository->findAll(),
            'spicyTypes' => $spicyTypeRepository->findAll(),
            'activeAgId' => $agId,
            'activeStId' => $stId,
            'activeSearch' => $search ?? '',
        ]);
    }

    #[Route('/{id<\d+>}', name: 'view_spice')]
    public function view(Spices $spice, SpiceViewRepository $spiceViewRepository, MessageBusInterface $bus): Response
    {
        /** @var Users|null $user */
        $user = $this->getUser();
        if ($user !== null) {
            $isNew = $spiceViewRepository->recordView($user, $spice);
            $bus->dispatch(new SpiceReadEvent($user->getId(), $spice->getId(), $isNew));
        }

        return $this->render('spices/view.html.twig', [
            'spice' => $spice,
            'relatedSpices' => $this->spicesRepository->findRelated($spice, 4),
        ]);
    }

    #[Route('/{id<\d+>}/apercu', name: 'quick_view_spice')]
    public function quickView(Spices $spice): Response
    {
        return $this->render('spices/_quick_view.html.twig', [
            'spice' => $spice,
        ]);
    }
}
