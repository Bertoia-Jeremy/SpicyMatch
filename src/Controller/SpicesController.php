<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
use App\Entity\Spices;
use App\Entity\Users;
use App\Message\SpiceReadEvent;
use App\Repository\AromaticGroupsRepository;
use App\Repository\SpiceDuoRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpiceViewRepository;
use App\Repository\SpicyTypeRepository;
use App\Routing\CatalogPath;
use App\Service\Match\SpiceDuoMapBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(CatalogPath::SPICES)]
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

        $aromaticGroup = $agSlug !== null ? $aromaticGroupsRepository->findOneByLocalizedSlug($agSlug, $locale) : null;
        $spicyType = $stSlug !== null ? $spicyTypeRepository->findOneByLocalizedSlug($stSlug, $locale) : null;

        $canonicalFilters = array_filter([
            'aromatic_group' => $agSlug !== null ? $aromaticGroup?->getLocalizedSlug($locale) : null,
            'spicy_type' => $stSlug !== null ? $spicyType?->getLocalizedSlug($locale) : null,
        ], static fn (?string $slug): bool => $slug !== null && $slug !== '');
        if (array_diff_assoc($canonicalFilters, $request->query->all()) !== []) {
            return $this->redirectToRoute(
                'index_spices',
                $canonicalFilters + $request->query->all(),
                Response::HTTP_MOVED_PERMANENTLY,
            );
        }

        $query = $this->spicesRepository->findFiltered($aromaticGroup?->getId(), $spicyType?->getId(), $search, $locale);

        $limit = $request->query->getInt('limit', 12);

        $spices = $paginator->paginate($query, $request->query->getInt('page', 1), $limit);

        return $this->render('spices/index.html.twig', [
            'spices' => $spices,
            'aromaticGroups' => $aromaticGroupsRepository->findAllForLocale($locale),
            'spicyTypes' => $spicyTypeRepository->findAllForLocale($locale),
            'activeAgId' => $aromaticGroup?->getId(),
            'activeStId' => $spicyType?->getId(),
            'activeAgSlug' => $aromaticGroup?->getLocalizedSlug($locale),
            'activeStSlug' => $spicyType?->getLocalizedSlug($locale),
            'activeSearch' => $search ?? '',
        ]);
    }

    #[Route('/{slug}', name: 'view_spice', requirements: [
        'slug' => CatalogPath::SPICE_SLUG_REQUIREMENT,
    ], priority: -10)]
    public function view(
        string $slug,
        Request $request,
        SpiceViewRepository $spiceViewRepository,
        MessageBusInterface $bus,
        SpiceDuoRepository $spiceDuoRepository,
        SpiceDuoMapBuilder $duoMapBuilder,
        #[CurrentUser]
        ?Users $user = null,
    ): Response {
        $locale = $request->getLocale();
        $spice = $this->spicesRepository->findOneByLocalizedSlug($slug, $locale);
        if (! $spice instanceof Spices) {
            throw $this->createNotFoundException();
        }

        if (($redirect = $this->canonicalSlugRedirect(
            'view_spice',
            $slug,
            $spice->getLocalizedSlug($locale),
            $locale
        )) instanceof RedirectResponse) {
            return $redirect;
        }

        if ($user instanceof Users) {
            $isNew = $spiceViewRepository->recordView($user, $spice);
            $bus->dispatch(new SpiceReadEvent($user->getId(), $spice->getId(), $isNew));
        }

        $duoRows = $spiceDuoRepository->findBySpiceIds([(int) $spice->getId()], $locale);

        return $this->render('spices/view.html.twig', [
            'spice' => $spice,
            'duosByCook' => $duoMapBuilder->tooltips($duoRows)['byCook'],
            'relatedSpices' => $this->spicesRepository->findRelated($spice, 4),
            'hreflang_slugs' => [
                'fr' => $spice->getLocalizedSlug('fr'),
                'en' => $spice->getLocalizedSlug('en'),
                'es' => $spice->getLocalizedSlug('es'),
            ],
        ]);
    }

    #[Route(CatalogPath::SPICE_QUICK_VIEW, name: 'quick_view_spice', priority: -10)]
    public function quickView(string $slug, Request $request): Response
    {
        $locale = $request->getLocale();
        $spice = $this->spicesRepository->findOneByLocalizedSlug($slug, $locale);
        if (! $spice instanceof Spices) {
            throw $this->createNotFoundException();
        }

        return $this->render('spices/_quick_view.html.twig', [
            'spice' => $spice,
        ]);
    }
}
