<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
use App\Repository\AromaticCompoundRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/epices/composes_aromatiques', defaults: [
    '_locale' => 'fr',
])]
class AromaticCompoundController extends AbstractController
{
    use CanonicalSlugTrait;

    #[Route('/', name: 'index_aromatic_compound')]
    public function index(AromaticCompoundRepository $repository): Response
    {
        return $this->render('aromatic_compound/index.html.twig', [
            'aromaticCompounds' => $repository->findAll(),
        ]);
    }

    #[Route('/{slug}', name: 'view_aromatic_compound')]
    public function view(string $slug, Request $request, AromaticCompoundRepository $repository): Response
    {
        $locale = $request->getLocale();
        $aromaticCompound = $repository->findOneByLocalizedSlug($slug, $locale);
        if (null === $aromaticCompound) {
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
