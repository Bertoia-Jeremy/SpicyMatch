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
        $agId = $request->query->getInt('aromatic_group') ?: null;
        $stId = $request->query->getInt('spicy_type') ?: null;

        $query = ($agId !== null || $stId !== null)
            ? $this->spicesRepository->findFiltered($agId, $stId)
            : $this->spicesRepository->findAll();

        $spices = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            12
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
