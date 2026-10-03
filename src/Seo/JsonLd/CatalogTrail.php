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

final readonly class CatalogTrail
{
    /**
     * @var array<class-string, array{index: string, label: string, crumb: string, view: string}>
     */
    private const array TRAILS = [
        Spices::class => [
            'index' => 'index_spices',
            'label' => 'ui.catalog.spices_title',
            'crumb' => 'ui.catalog.breadcrumb_spices',
            'view' => 'view_spice',
        ],
        AromaticCompound::class => [
            'index' => 'index_aromatic_compound',
            'label' => 'ui.catalog.compounds_title',
            'crumb' => 'ui.catalog.compounds_title',
            'view' => 'view_aromatic_compound',
        ],
        AromaticGroups::class => [
            'index' => 'index_aromatic_groups',
            'label' => 'ui.catalog.groups_title',
            'crumb' => 'ui.catalog.groups_title',
            'view' => 'view_aromatic_groups',
        ],
        AlchemyFlavors::class => [
            'index' => 'index_alchemy_flavors',
            'label' => 'ui.catalog.flavors_title',
            'crumb' => 'ui.catalog.flavors_title',
            'view' => 'view_alchemy_flavors',
        ],
        SpicyType::class => [
            'index' => 'index_spicy_type',
            'label' => 'ui.catalog.types_title',
            'crumb' => 'ui.catalog.types_title',
            'view' => 'view_spicy_type',
        ],
        PreparationMethods::class => [
            'index' => 'index_preparation_methods',
            'label' => 'ui.catalog.methods_title',
            'crumb' => 'ui.catalog.methods_title',
            'view' => 'view_preparation_methods',
        ],
    ];

    public function __construct(
        private UrlGeneratorInterface $router,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @phpstan-assert-if-true Spices|AromaticCompound|AromaticGroups|AlchemyFlavors|SpicyType|PreparationMethods $subject
     */
    public function supports(?object $subject): bool
    {
        return $subject !== null && isset(self::TRAILS[$subject::class]);
    }

    public function name(Spices|AromaticCompound|AromaticGroups|AlchemyFlavors|SpicyType|PreparationMethods $subject, string $locale): string
    {
        return (string) $subject->getLocalizedName($locale);
    }

    public function url(Spices|AromaticCompound|AromaticGroups|AlchemyFlavors|SpicyType|PreparationMethods $subject, string $locale, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): string
    {
        return $this->generate(self::TRAILS[$subject::class]['view'], $locale, $referenceType, [
            'slug' => (string) $subject->getLocalizedSlug($locale),
        ]);
    }

    public function indexName(Spices|AromaticCompound|AromaticGroups|AlchemyFlavors|SpicyType|PreparationMethods $subject, string $locale): string
    {
        return $this->translator->trans(self::TRAILS[$subject::class]['label'], locale: $locale);
    }

    public function indexUrl(Spices|AromaticCompound|AromaticGroups|AlchemyFlavors|SpicyType|PreparationMethods $subject, string $locale, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): string
    {
        return $this->generate(self::TRAILS[$subject::class]['index'], $locale, $referenceType);
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    public function crumbs(Spices|AromaticCompound|AromaticGroups|AlchemyFlavors|SpicyType|PreparationMethods $subject, string $locale, int $referenceType = UrlGeneratorInterface::ABSOLUTE_URL): array
    {
        return [
            [
                'name' => $this->translator->trans('ui.common.home', locale: $locale),
                'url' => $this->generate('home', $locale, $referenceType),
            ],
            [
                'name' => $this->translator->trans(self::TRAILS[$subject::class]['crumb'], locale: $locale),
                'url' => $this->indexUrl($subject, $locale, $referenceType),
            ],
            [
                'name' => $this->name($subject, $locale),
                'url' => $this->url($subject, $locale, $referenceType),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function termSet(Spices|AromaticCompound|AromaticGroups|AlchemyFlavors|SpicyType|PreparationMethods $subject, string $locale): array
    {
        return [
            '@type' => 'DefinedTermSet',
            'name' => $this->indexName($subject, $locale),
            'url' => $this->indexUrl($subject, $locale),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    private function generate(string $route, string $locale, int $referenceType, array $params = []): string
    {
        return $this->router->generate($route, [
            '_locale' => $locale,
        ] + $params, $referenceType);
    }
}
