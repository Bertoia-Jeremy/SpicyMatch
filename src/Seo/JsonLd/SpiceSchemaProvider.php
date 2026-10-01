<?php

declare(strict_types=1);

namespace App\Seo\JsonLd;

use App\Entity\Spices;
use App\Seo\SeoText;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

final readonly class SpiceSchemaProvider implements SchemaProviderInterface
{
    public const string IMAGE_FILTER = 'spice_hero';

    public function __construct(
        private UrlGeneratorInterface $router,
        private StorageInterface $storage,
        private CacheManager $imagineCache,
    ) {
    }

    public function supports(?object $subject): bool
    {
        return $subject instanceof Spices;
    }

    public function build(?object $subject, string $locale): array
    {
        \assert($subject instanceof Spices);

        $schema = [
            '@type' => 'Thing',
            'name' => (string) $subject->getLocalizedName($locale),
            'url' => $this->router->generate('view_spice', [
                '_locale' => $locale,
                'slug' => (string) $subject->getLocalizedSlug($locale),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        $description = SeoText::summary($subject->getLocalizedDescription($locale));
        if ($description !== '') {
            $schema['description'] = $description;
        }

        $image = $this->storage->resolveUri($subject, 'imageFile');
        if ($image !== null) {
            $schema['image'] = $this->imagineCache->getBrowserPath($image, self::IMAGE_FILTER);
        }

        return $schema;
    }
}
