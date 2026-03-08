<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Spices;
use App\Repository\AromaticGroupsRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyTypeRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/epices')]
class SpicesController extends AbstractController
{
    public function __construct(
        private SpicesRepository $spicesRepository
    ) {
    }

    #[Route('/', name: 'index_spices')]
    public function index(
        Request $request,
        PaginatorInterface $paginator,
        AromaticGroupsRepository $aromaticGroupsRepository,
        SpicyTypeRepository $spicyTypeRepository,
    ): Response {
        $agId = filter_var($request->query->get('aromatic_group'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?: null;
        $stId = filter_var($request->query->get('spicy_type'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) ?: null;

        $query = ($agId !== null || $stId !== null)
            ? $this->spicesRepository->findFiltered($agId, $stId)
            : $this->spicesRepository->findAll();

        $limit = $request->query->getInt('limit', 12);

        $spices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            $limit
        );

        return $this->render('spices/index.html.twig', [
            'spices'         => $spices,
            'aromaticGroups' => $aromaticGroupsRepository->findAll(),
            'spicyTypes'     => $spicyTypeRepository->findAll(),
            'activeAgId'     => $agId,
            'activeStId'     => $stId,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'view_spice')]
    public function view(Spices $spice): Response
    {
        return $this->render('spices/view.html.twig', [
            'spice' => $spice,
        ]);
    }
}
