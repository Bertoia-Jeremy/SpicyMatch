<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
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

#[Route('/{_locale}/epices', defaults: [
    '_locale' => 'fr',
])]
class SpicesController extends AbstractController
{
    use CanonicalSlugTrait;

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
        $locale = $request->getLocale();
        $agSlug = trim((string) $request->query->get('aromatic_group', '')) ?: null;
        $stSlug = trim((string) $request->query->get('spicy_type', '')) ?: null;
        $search = trim((string) $request->query->get('search', '')) ?: null;

        $aromaticGroup = null !== $agSlug ? $aromaticGroupsRepository->findOneByLocalizedSlug($agSlug, $locale) : null;
        $spicyType = null !== $stSlug ? $spicyTypeRepository->findOneByLocalizedSlug($stSlug, $locale) : null;

        // findFiltered(null, null, null) retourne toutes les épices avec eager-load des relations.
        // Remplace findAll() qui déclenchait du N+1 en Twig sur aromaticGroups / spicyType.
        $query = $this->spicesRepository->findFiltered($aromaticGroup?->getId(), $spicyType?->getId(), $search);

        $limit = $request->query->getInt('limit', 12);

        $spices = $paginator->paginate($query, $request->query->getInt('page', 1), $limit);

        return $this->render('spices/index.html.twig', [
            'spices' => $spices,
            'aromaticGroups' => $aromaticGroupsRepository->findAll(),
            'spicyTypes' => $spicyTypeRepository->findAll(),
            'activeAgId' => $aromaticGroup?->getId(),
            'activeStId' => $spicyType?->getId(),
            'activeAgSlug' => $aromaticGroup?->getLocalizedSlug($locale),
            'activeStSlug' => $spicyType?->getLocalizedSlug($locale),
            'activeSearch' => $search ?? '',
        ]);
    }

    #[Route('/{slug}', name: 'view_spice', priority: -10, requirements: [
        'slug' => '(?!(?:groupes_aromatiques|composes_aromatiques|saveurs_aromatiques|types_epices)$)[^/]+',
    ])]
    public function view(
        string $slug,
        Request $request,
        SpiceViewRepository $spiceViewRepository,
        MessageBusInterface $bus,
    ): Response {
        $locale = $request->getLocale();
        $spice = $this->spicesRepository->findOneByLocalizedSlug($slug, $locale);
        if (null === $spice) {
            throw $this->createNotFoundException();
        }

        if (($redirect = $this->canonicalSlugRedirect(
            'view_spice',
            $slug,
            $spice->getLocalizedSlug($locale),
            $locale
        )) !== null) {
            return $redirect;
        }

        /** @var Users|null $user */
        $user = $this->getUser();
        if (null !== $user) {
            $isNew = $spiceViewRepository->recordView($user, $spice);
            $bus->dispatch(new SpiceReadEvent($user->getId(), $spice->getId(), $isNew));
        }

        return $this->render('spices/view.html.twig', [
            'spice' => $spice,
            'relatedSpices' => $this->spicesRepository->findRelated($spice, 4),
            'hreflang_slugs' => [
                'fr' => $spice->getLocalizedSlug('fr'),
                'en' => $spice->getLocalizedSlug('en'),
                'es' => $spice->getLocalizedSlug('es'),
            ],
        ]);
    }

    #[Route('/{slug}/apercu', name: 'quick_view_spice', priority: -10)]
    public function quickView(string $slug, Request $request): Response
    {
        $locale = $request->getLocale();
        $spice = $this->spicesRepository->findOneByLocalizedSlug($slug, $locale);
        if (null === $spice) {
            throw $this->createNotFoundException();
        }

        return $this->render('spices/_quick_view.html.twig', [
            'spice' => $spice,
        ]);
    }
}
