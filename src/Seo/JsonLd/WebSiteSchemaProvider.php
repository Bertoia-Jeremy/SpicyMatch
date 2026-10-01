<?php

declare(strict_types=1);

namespace App\Seo\JsonLd;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class WebSiteSchemaProvider implements SchemaProviderInterface
{
    public const string SITE_NAME = 'SpicyMatch';

    public function __construct(
        private UrlGeneratorInterface $router,
    ) {
    }

    public function supports(?object $subject): bool
    {
        return $subject === null;
    }

    public function build(?object $subject, string $locale): array
    {
        $search = $this->router->generate('search_results', [
            '_locale' => $locale,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            '@type' => 'WebSite',
            'name' => self::SITE_NAME,
            'url' => $this->router->generate('home', [
                '_locale' => $locale,
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            'inLanguage' => $locale,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $search . '?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }
}
