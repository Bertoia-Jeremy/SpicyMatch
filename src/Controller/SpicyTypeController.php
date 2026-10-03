<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
use App\Repository\SpicesRepository;
use App\Repository\SpicyTypeRepository;
use App\Routing\CatalogPath;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(CatalogPath::SPICY_TYPES)]
class SpicyTypeController extends AbstractController
{
    use CanonicalSlugTrait;

    #[Route('/', name: 'index_spicy_type', methods: ['GET'])]
    public function index(SpicyTypeRepository $repository, Request $request): Response
    {
        return $this->render('spicy_type/index.html.twig', [
            'spicyTypes' => $repository->findAllForLocale($request->getLocale()),
        ]);
    }

    #[Route('/{slug}', name: 'view_spicy_type', methods: ['GET'])]
    public function view(
        string $slug,
        Request $request,
        SpicyTypeRepository $repository,
        SpicesRepository $spicesRepository,
    ): Response {
        $locale = $request->getLocale();
        $spicyType = $repository->findOneByLocalizedSlug($slug, $locale);
        if ($spicyType === null) {
            throw $this->createNotFoundException();
        }

        if (($redirect = $this->canonicalSlugRedirect(
            'view_spicy_type',
            $slug,
            $spicyType->getLocalizedSlug($locale),
            $locale
        )) !== null) {
            return $redirect;
        }

        return $this->render('spicy_type/view.html.twig', [
            'spicyType' => $spicyType,
            'spices' => $spicesRepository->findFiltered(null, $spicyType->getId(), null, $locale),
            'types' => $repository->findAllForLocale($locale),
            'hreflang_slugs' => [
                'fr' => $spicyType->getLocalizedSlug('fr'),
                'en' => $spicyType->getLocalizedSlug('en'),
                'es' => $spicyType->getLocalizedSlug('es'),
            ],
        ]);
    }
}
