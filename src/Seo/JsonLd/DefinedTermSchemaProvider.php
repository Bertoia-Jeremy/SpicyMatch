<?php

declare(strict_types=1);

namespace App\Seo\JsonLd;

use App\Entity\AromaticCompound;
use App\Entity\Spices;
use App\Seo\SeoText;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Storage\StorageInterface;

final readonly class DefinedTermSchemaProvider implements SchemaProviderInterface
{
    public const string IMAGE_FILTER = 'spice_hero';

    public function __construct(
        private CatalogTrail $trail,
        private StorageInterface $storage,
        private CacheManager $imagineCache,
    ) {
    }

    public function supports(?object $subject): bool
    {
        return $this->trail->supports($subject) && ! $subject instanceof AromaticCompound;
    }

    public function build(?object $subject, string $locale): array
    {
        \assert($this->trail->supports($subject));

        $schema = [
            '@type' => 'DefinedTerm',
            'name' => $this->trail->name($subject, $locale),
            'url' => $this->trail->url($subject, $locale),
        ];

        $description = SeoText::summary($subject->getLocalizedDescription($locale));
        if ($description !== '') {
            $schema['description'] = $description;
        }

        if ($subject instanceof Spices) {
            $image = $this->storage->resolveUri($subject, 'imageFile');
            if ($image !== null) {
                $schema['image'] = $this->imagineCache->getBrowserPath($image, self::IMAGE_FILTER);
            }
        }

        $schema['inDefinedTermSet'] = $this->trail->termSet($subject, $locale);

        return $schema;
    }
}
