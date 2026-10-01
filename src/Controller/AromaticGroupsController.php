<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
use App\Repository\AromaticGroupsRepository;
use App\Repository\SpicesRepository;
use App\Routing\CatalogPath;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(CatalogPath::AROMATIC_GROUPS)]
class AromaticGroupsController extends AbstractController
{
    use CanonicalSlugTrait;

    #[Route('/', name: 'index_aromatic_groups', methods: ['GET'])]
    public function index(AromaticGroupsRepository $repository, Request $request): Response
    {
        return $this->render('aromatic_groups/index.html.twig', [
            'aromaticGroups' => $repository->findAllForLocale($request->getLocale()),
        ]);
    }

    #[Route('/{slug}', name: 'view_aromatic_groups', methods: ['GET'])]
    public function view(
        string $slug,
        Request $request,
        AromaticGroupsRepository $repository,
        SpicesRepository $spicesRepository,
    ): Response {
        $locale = $request->getLocale();
        $aromaticGroup = $repository->findOneByLocalizedSlug($slug, $locale);
        if ($aromaticGroup === null) {
            throw $this->createNotFoundException();
        }

        if (($redirect = $this->canonicalSlugRedirect(
            'view_aromatic_groups',
            $slug,
            $aromaticGroup->getLocalizedSlug($locale),
            $locale
        )) !== null) {
            return $redirect;
        }

        return $this->render('aromatic_groups/view.html.twig', [
            'aromaticGroup' => $aromaticGroup,
            'spices' => $spicesRepository->findFiltered($aromaticGroup->getId(), null, null, $locale),
            'hreflang_slugs' => [
                'fr' => $aromaticGroup->getLocalizedSlug('fr'),
                'en' => $aromaticGroup->getLocalizedSlug('en'),
                'es' => $aromaticGroup->getLocalizedSlug('es'),
            ],
        ]);
    }
}
