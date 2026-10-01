<?php

namespace App\Repository;

use App\Entity\PreparationMethods;
use App\Repository\Concern\LocalizedSlugLookupTrait;
use App\Seo\SitemapSourceInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreparationMethods>
 */
class PreparationMethodsRepository extends ServiceEntityRepository implements SitemapSourceInterface
{
    use LocalizedSlugLookupTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreparationMethods::class);
    }

    public function findOneByLocalizedSlug(string $slug, string $locale): ?PreparationMethods
    {
        $entity = $this->lookupByLocalizedSlug($slug, $locale);

        return $entity instanceof PreparationMethods ? $entity : null;
    }

    /**
     * @return list<PreparationMethods>
     */
    public function findAllForLocale(string $locale): array
    {
        return array_values(array_filter(
            $this->findAllWithTranslation($locale),
            static fn (object $e): bool => $e instanceof PreparationMethods,
        ));
    }
}
