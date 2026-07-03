<?php

declare(strict_types=1);

namespace App\Controller\Concern;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @mixin \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
 */
trait CanonicalSlugTrait
{
    /**
     * @param array<string, mixed> $extra
     */
    protected function canonicalSlugRedirect(
        string $routeName,
        string $requestedSlug,
        ?string $canonicalSlug,
        string $locale,
        array $extra = [],
    ): ?RedirectResponse {
        if (null === $canonicalSlug || '' === $canonicalSlug || $canonicalSlug === $requestedSlug) {
            return null;
        }

        return $this->redirectToRoute(
            $routeName,
            [
                '_locale' => $locale,
                'slug' => $canonicalSlug,
            ] + $extra,
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }
}
