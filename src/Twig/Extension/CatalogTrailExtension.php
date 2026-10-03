<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Seo\JsonLd\CatalogTrail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class CatalogTrailExtension
{
    public function __construct(
        private CatalogTrail $trail,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    #[AsTwigFunction(name: 'catalog_crumbs')]
    public function crumbs(object $subject): array
    {
        if (! $this->trail->hasCrumbs($subject)) {
            return [];
        }

        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';

        return $this->trail->crumbs($subject, $locale, UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
