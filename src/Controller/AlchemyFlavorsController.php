<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
use App\Repository\AlchemyFlavorsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/epices/saveurs_aromatiques', defaults: [
    '_locale' => 'fr',
])]
class AlchemyFlavorsController extends AbstractController
{
    use CanonicalSlugTrait;

    #[Route('/', name: 'index_alchemy_flavors')]
    public function index(AlchemyFlavorsRepository $repository): Response
    {
        return $this->render('alchemy_flavors/index.html.twig', [
            'alchemyFlavors' => $repository->findAll(),
        ]);
    }

    #[Route('/{slug}', name: 'view_alchemy_flavors')]
    public function view(string $slug, Request $request, AlchemyFlavorsRepository $repository): Response
    {
        $locale = $request->getLocale();
        $alchemyFlavor = $repository->findOneByLocalizedSlug($slug, $locale);
        if (null === $alchemyFlavor) {
            throw $this->createNotFoundException();
        }

        if (($redirect = $this->canonicalSlugRedirect(
            'view_alchemy_flavors',
            $slug,
            $alchemyFlavor->getLocalizedSlug($locale),
            $locale
        )) !== null) {
            return $redirect;
        }

        return $this->render('alchemy_flavors/view.html.twig', [
            'alchemyFlavor' => $alchemyFlavor,
            'hreflang_slugs' => [
                'fr' => $alchemyFlavor->getLocalizedSlug('fr'),
                'en' => $alchemyFlavor->getLocalizedSlug('en'),
                'es' => $alchemyFlavor->getLocalizedSlug('es'),
            ],
        ]);
    }
}
