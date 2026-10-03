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

        $items = [
            [$this->trail->homeName($locale), $this->trail->homeUrl($locale)],
            [$this->trail->indexName($subject, $locale), $this->trail->indexUrl($subject, $locale)],
            [$this->trail->name($subject, $locale), $this->trail->url($subject, $locale)],
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
}
