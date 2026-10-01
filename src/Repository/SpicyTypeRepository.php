<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpicyType;
use App\Repository\Concern\LocalizedSlugLookupTrait;
use App\Seo\SitemapSourceInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpicyType>
 */
class SpicyTypeRepository extends ServiceEntityRepository implements SitemapSourceInterface
{
    use LocalizedSlugLookupTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpicyType::class);
    }

    public function add(SpicyType $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->persist($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function remove(SpicyType $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function findOneByLocalizedSlug(string $slug, string $locale): ?SpicyType
    {
        $entity = $this->lookupByLocalizedSlug($slug, $locale);

        return $entity instanceof SpicyType ? $entity : null;
    }

    /**
     * @return list<SpicyType>
     */
    public function findAllForLocale(string $locale): array
    {
        return array_values(array_filter(
            $this->findAllWithTranslation($locale),
            static fn (object $e): bool => $e instanceof SpicyType,
        ));
    }
}
