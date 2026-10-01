<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Seo\JsonLd\SchemaProviderInterface;
use App\Seo\SeoText;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Markup;

final readonly class SeoExtension
{
    public const int JSON_FLAGS = \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

    /**
     * @param iterable<SchemaProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(SchemaProviderInterface::class)]
        private iterable $providers,
        private RequestStack $requestStack,
    ) {
    }

    #[AsTwigFunction(name: 'json_ld')]
    public function jsonLd(?object $subject = null): Markup
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';

        $schemas = [];
        foreach ($this->providers as $provider) {
            if ($provider->supports($subject)) {
                $schemas[] = $provider->build($subject, $locale);
            }
        }

        if ($schemas === []) {
            return new Markup('', 'UTF-8');
        }

        $document = \count($schemas) === 1
            ? [
                '@context' => 'https://schema.org',
            ] + $schemas[0]
            : [
                '@context' => 'https://schema.org',
                '@graph' => $schemas,
            ];

        return new Markup(json_encode($document, self::JSON_FLAGS), 'UTF-8');
    }

    #[AsTwigFilter(name: 'seo_summary')]
    public function summary(?string $text): string
    {
        return SeoText::summary($text);
    }
}
