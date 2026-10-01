<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Concern\CanonicalSlugTrait;
use App\Entity\Spices;
use App\Repository\AromaticGroupsRepository;
use App\Repository\SpiceDuoRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyTypeRepository;
use App\Routing\CatalogPath;
use App\Service\Education\AcademyManager;
use App\Service\Match\SpiceDuoMapBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(CatalogPath::SPICES)]
class SpicesController extends AbstractController
{
    use CanonicalSlugTrait;

    private const int RAIL_SIZE = 4;

    private const int COMPATIBLE_POOL = 8;

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
        SpiceDuoRepository $spiceDuoRepository,
        SpiceDuoMapBuilder $duoMapBuilder,
        AcademyManager $academyManager,
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

        $spiceId = (int) $spice->getId();
        $duoRows = $spiceDuoRepository->findBySpiceIds([$spiceId], $locale);

        $compatibleIds = array_column(array_slice($academyManager->findCompatibleSpices($spice), 0, self::COMPATIBLE_POOL), 'id');
        $compatibleSpices = array_slice($this->spicesRepository->findActiveByIdsInOrder($compatibleIds), 0, self::RAIL_SIZE);
        $compatibleSpiceIds = array_map(static fn (Spices $s): int => (int) $s->getId(), $compatibleSpices);

        return $this->render('spices/view.html.twig', [
            'spice' => $spice,
            'duosByCook' => $duoMapBuilder->tooltips($duoRows)['byCook'],
            'compatibleSpices' => $compatibleSpices,
            'relatedSpices' => $this->spicesRepository->findRelated($spice, self::RAIL_SIZE, $compatibleSpiceIds),
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
