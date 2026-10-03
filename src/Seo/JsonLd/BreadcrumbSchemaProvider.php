<?php

declare(strict_types=1);

namespace App\Seo\JsonLd;

final readonly class BreadcrumbSchemaProvider implements SchemaProviderInterface
{
    public function __construct(
        private CatalogTrail $trail,
    ) {
    }

    public function supports(?object $subject): bool
    {
        return $this->trail->supports($subject);
    }

    public function build(?object $subject, string $locale): array
    {
        \assert($this->trail->supports($subject));

        $elements = [];
        foreach ($this->trail->crumbs($subject, $locale) as $position => $crumb) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }
}
