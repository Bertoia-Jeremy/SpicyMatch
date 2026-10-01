<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\EventSubscriber\LocaleSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class LocaleAlternatesExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    #[AsTwigFunction(name: 'locale_alternates', needsContext: true)]
    public function alternates(array $context, bool $absolute = false): array
    {
        $request = $this->requestStack->getMainRequest();
        if ($request === null) {
            return [];
        }

        $route = $request->attributes->get('_canonical_route') ?? $request->attributes->get('_route');
        $params = $request->attributes->get('_route_params');
        if (! \is_string($route) || ! \is_array($params) || ! isset($params['_locale'])) {
            return [];
        }

        unset($params['_canonical_route']);
        $slugs = $context['hreflang_slugs'] ?? null;
        $referenceType = $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH;

        $urls = [];
        foreach (LocaleSubscriber::SUPPORTED_LOCALES as $locale) {
            $localeParams = [
                '_locale' => $locale,
            ] + $params;
            if (\is_array($slugs) && isset($params['slug'])) {
                $slug = $slugs[$locale] ?? null;
                if (! \is_string($slug) || $slug === '') {
                    continue;
                }

                $localeParams['slug'] = $slug;
            }

            $urls[$locale] = $this->urlGenerator->generate($route, $localeParams, $referenceType);
        }

        return $urls;
    }
}
