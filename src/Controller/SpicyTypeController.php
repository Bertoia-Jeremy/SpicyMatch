<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
use App\Repository\SpicyTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/epices/types_epices', defaults: [
    '_locale' => 'fr',
])]
class SpicyTypeController extends AbstractController
{
    use CanonicalSlugTrait;

    #[Route('/', name: 'index_spicy_type', methods: ['GET'])]
    public function index(SpicyTypeRepository $repository): Response
    {
        return $this->render('spicy_type/index.html.twig', [
            'spicyTypes' => $repository->findAll(),
        ]);
    }

    #[Route('/{slug}', name: 'view_spicy_type', methods: ['GET'])]
    public function view(string $slug, Request $request, SpicyTypeRepository $repository): Response
    {
        $locale = $request->getLocale();
        $spicyType = $repository->findOneByLocalizedSlug($slug, $locale);
        if (null === $spicyType) {
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
            'hreflang_slugs' => [
                'fr' => $spicyType->getLocalizedSlug('fr'),
                'en' => $spicyType->getLocalizedSlug('en'),
                'es' => $spicyType->getLocalizedSlug('es'),
            ],
        ]);
    }
}
