<?php

declare(strict_types=1);

namespace App\Seo\JsonLd;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use App\Entity\AromaticGroups;
use App\Entity\PreparationMethods;
use App\Entity\Spices;
use App\Entity\SpicyType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class BreadcrumbSchemaProvider implements SchemaProviderInterface
{
    /**
     * @var array<class-string, array{index: string, label: string, view: string}>
     */
    private const array TRAILS = [
        Spices::class => [
            'index' => 'index_spices',
            'label' => 'ui.catalog.spices_title',
            'view' => 'view_spice',
        ],
        AromaticCompound::class => [
            'index' => 'index_aromatic_compound',
            'label' => 'ui.catalog.compounds_title',
            'view' => 'view_aromatic_compound',
        ],
        AromaticGroups::class => [
            'index' => 'index_aromatic_groups',
            'label' => 'ui.catalog.groups_title',
            'view' => 'view_aromatic_groups',
        ],
        AlchemyFlavors::class => [
            'index' => 'index_alchemy_flavors',
            'label' => 'ui.catalog.flavors_title',
            'view' => 'view_alchemy_flavors',
        ],
        SpicyType::class => [
            'index' => 'index_spicy_type',
            'label' => 'ui.catalog.types_title',
            'view' => 'view_spicy_type',
        ],
        PreparationMethods::class => [
            'index' => 'index_preparation_methods',
            'label' => 'ui.catalog.methods_title',
            'view' => 'view_preparation_methods',
        ],
    ];

    public function __construct(
        private UrlGeneratorInterface $router,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(?object $subject): bool
    {
        return $subject !== null && isset(self::TRAILS[$subject::class]);
    }

    public function build(?object $subject, string $locale): array
    {
        \assert($subject instanceof Spices || $subject instanceof AromaticCompound || $subject instanceof AromaticGroups || $subject instanceof AlchemyFlavors || $subject instanceof SpicyType || $subject instanceof PreparationMethods);
        $trail = self::TRAILS[$subject::class];

        $items = [
            [$this->translator->trans('ui.common.home', locale: $locale), $this->url('home', $locale)],
            [$this->translator->trans($trail['label'], locale: $locale), $this->url($trail['index'], $locale)],
            [(string) $subject->getLocalizedName($locale), $this->url($trail['view'], $locale, [
                'slug' => (string) $subject->getLocalizedSlug($locale),
            ])],
        ];

        $elements = [];
        foreach ($items as $position => [$name, $url]) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $name,
                'item' => $url,
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param array<string, string> $params
     */
    private function url(string $route, string $locale, array $params = []): string
    {
        return $this->router->generate($route, [
            '_locale' => $locale,
        ] + $params, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
