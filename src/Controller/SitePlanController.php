<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AlchemyFlavorsRepository;
use App\Repository\AromaticGroupsRepository;
use App\Repository\PreparationMethodsRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyTypeRepository;
use App\ValueObject\PageTrail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SitePlanController extends AbstractController
{
    public const array PATHS = [
        'fr' => '/fr/plan-du-site',
        'en' => '/en/site-map',
        'es' => '/es/mapa-del-sitio',
    ];

    public function __construct(
        private readonly SpicesRepository $spices,
        private readonly AromaticGroupsRepository $aromaticGroups,
        private readonly AlchemyFlavorsRepository $flavors,
        private readonly SpicyTypeRepository $spicyTypes,
        private readonly PreparationMethodsRepository $preparationMethods,
    ) {
    }

    #[Route(self::PATHS, name: 'site_plan', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $locale = $request->getLocale();

        return $this->render('site_plan/index.html.twig', [
            'trail' => new PageTrail('site_plan', 'ui.site_plan.title'),
            'helpTopics' => HelpController::KNOWN_TOPICS,
            'catalog' => [
                [
                    'label' => 'ui.footer.spices',
                    'index' => 'index_spices',
                    'view' => 'view_spice',
                    'links' => $this->spices->findSitePlanLinks($locale),
                ],
                [
                    'label' => 'ui.footer.aromatic_groups',
                    'index' => 'index_aromatic_groups',
                    'view' => 'view_aromatic_groups',
                    'links' => $this->aromaticGroups->findSitePlanLinks($locale),
                ],
                [
                    'label' => 'ui.footer.flavors',
                    'index' => 'index_alchemy_flavors',
                    'view' => 'view_alchemy_flavors',
                    'links' => $this->flavors->findSitePlanLinks($locale),
                ],
                [
                    'label' => 'ui.footer.spicy_types',
                    'index' => 'index_spicy_type',
                    'view' => 'view_spicy_type',
                    'links' => $this->spicyTypes->findSitePlanLinks($locale),
                ],
                [
                    'label' => 'ui.footer.preparation_methods',
                    'index' => 'index_preparation_methods',
                    'view' => 'view_preparation_methods',
                    'links' => $this->preparationMethods->findSitePlanLinks($locale),
                ],
                [
                    'label' => 'ui.footer.aromatic_compounds',
                    'index' => 'index_aromatic_compound',
                    'view' => null,
                    'links' => [],
                ],
            ],
        ]);
    }
}
