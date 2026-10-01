<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
use App\Repository\AromaticCompoundRepository;
use App\Routing\CatalogPath;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(CatalogPath::AROMATIC_COMPOUNDS)]
class AromaticCompoundController extends AbstractController
{
    use CanonicalSlugTrait;

    #[Route('/', name: 'index_aromatic_compound')]
    public function index(AromaticCompoundRepository $repository, Request $request): Response
    {
        return $this->render('aromatic_compound/index.html.twig', [
            'aromaticCompounds' => $repository->findAllForLocale($request->getLocale()),
        ]);
    }

    #[Route('/{slug}', name: 'view_aromatic_compound')]
    public function view(string $slug, Request $request, AromaticCompoundRepository $repository): Response
    {
        $locale = $request->getLocale();
        $aromaticCompound = $repository->findOneByLocalizedSlug($slug, $locale);
        if ($aromaticCompound === null) {
            throw $this->createNotFoundException();
        }

        if (($redirect = $this->canonicalSlugRedirect(
            'view_aromatic_compound',
            $slug,
            $aromaticCompound->getLocalizedSlug($locale),
            $locale
        )) !== null) {
            return $redirect;
        }

        return $this->render('aromatic_compound/view.html.twig', [
            'aromaticCompound' => $aromaticCompound,
            'hreflang_slugs' => [
                'fr' => $aromaticCompound->getLocalizedSlug('fr'),
                'en' => $aromaticCompound->getLocalizedSlug('en'),
                'es' => $aromaticCompound->getLocalizedSlug('es'),
            ],
        ]);
    }
}
