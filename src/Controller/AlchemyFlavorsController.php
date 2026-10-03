<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
use App\Entity\AromaticCompound;
use App\Repository\AlchemyFlavorsRepository;
use App\Repository\AromaticCompoundRepository;
use App\Repository\SpicesRepository;
use App\Routing\CatalogPath;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(CatalogPath::FLAVORS)]
class AlchemyFlavorsController extends AbstractController
{
    use CanonicalSlugTrait;

    #[Route('/', name: 'index_alchemy_flavors')]
    public function index(AlchemyFlavorsRepository $repository, Request $request): Response
    {
        return $this->render('alchemy_flavors/index.html.twig', [
            'alchemyFlavors' => $repository->findAllForLocale($request->getLocale()),
        ]);
    }

    #[Route('/{slug}', name: 'view_alchemy_flavors')]
    public function view(
        string $slug,
        Request $request,
        AlchemyFlavorsRepository $repository,
        AromaticCompoundRepository $compoundRepository,
        SpicesRepository $spicesRepository,
    ): Response {
        $locale = $request->getLocale();
        $alchemyFlavor = $repository->findOneByLocalizedSlug($slug, $locale);
        if ($alchemyFlavor === null) {
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

        $compounds = $compoundRepository->findForFlavor($alchemyFlavor, $locale);

        return $this->render('alchemy_flavors/view.html.twig', [
            'alchemyFlavor' => $alchemyFlavor,
            'compounds' => $compounds,
            'carriers' => $spicesRepository->findByCompoundIds(
                array_values(array_filter(array_map(static fn (AromaticCompound $c): ?int => $c->getId(), $compounds))),
                $locale,
            ),
            'hreflang_slugs' => [
                'fr' => $alchemyFlavor->getLocalizedSlug('fr'),
                'en' => $alchemyFlavor->getLocalizedSlug('en'),
                'es' => $alchemyFlavor->getLocalizedSlug('es'),
            ],
        ]);
    }
}
