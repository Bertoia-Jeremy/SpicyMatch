<?php

declare(strict_types=1);

namespace App\Routing;

final class CatalogPath
{
    public const array SPICES = [
        'fr' => '/fr/epices',
        'en' => '/en/spices',
        'es' => '/es/especias',
    ];

    public const array AROMATIC_GROUPS = [
        'fr' => '/fr/epices/groupes-aromatiques',
        'en' => '/en/spices/aromatic-groups',
        'es' => '/es/especias/grupos-aromaticos',
    ];

    public const array AROMATIC_COMPOUNDS = [
        'fr' => '/fr/epices/composes-aromatiques',
        'en' => '/en/spices/aromatic-compounds',
        'es' => '/es/especias/compuestos-aromaticos',
    ];

    public const array FLAVORS = [
        'fr' => '/fr/epices/saveurs-aromatiques',
        'en' => '/en/spices/aromatic-flavors',
        'es' => '/es/especias/sabores-aromaticos',
    ];

    public const array SPICY_TYPES = [
        'fr' => '/fr/epices/types-epices',
        'en' => '/en/spices/spice-types',
        'es' => '/es/especias/tipos-especias',
    ];

    public const array PREPARATION_METHODS = [
        'fr' => '/fr/methodes-preparation',
        'en' => '/en/preparation-methods',
        'es' => '/es/metodos-preparacion',
    ];

    public const array SPICE_QUICK_VIEW = [
        'fr' => '/{slug}/apercu',
        'en' => '/{slug}/preview',
        'es' => '/{slug}/vista-previa',
    ];

    public const string SPICE_SLUG_REQUIREMENT = '(?!(?:groupes-aromatiques|composes-aromatiques|saveurs-aromatiques|types-epices'
        . '|aromatic-groups|aromatic-compounds|aromatic-flavors|spice-types'
        . '|grupos-aromaticos|compuestos-aromaticos|sabores-aromaticos|tipos-especias)$)[^/]+';

    /**
     * @return list<array<string, string>>
     */
    public static function spiceSubsections(): array
    {
        return [self::AROMATIC_GROUPS, self::AROMATIC_COMPOUNDS, self::FLAVORS, self::SPICY_TYPES];
    }
}
