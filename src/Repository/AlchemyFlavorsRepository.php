<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use App\Repository\Concern\LocalizedSlugLookupTrait;
use App\Seo\SitemapSourceInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<AlchemyFlavors>
 */
class AlchemyFlavorsRepository extends ServiceEntityRepository implements SitemapSourceInterface
{
    use LocalizedSlugLookupTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlchemyFlavors::class);
    }

    public function add(AlchemyFlavors $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->persist($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function remove(AlchemyFlavors $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function findOneByLocalizedSlug(string $slug, string $locale): ?AlchemyFlavors
    {
        $entity = $this->lookupByLocalizedSlug($slug, $locale);

        return $entity instanceof AlchemyFlavors ? $entity : null;
    }

    /**
     * @return list<AlchemyFlavors>
     */
    public function findAllForLocale(string $locale): array
    {
        return array_values(array_filter(
            $this->findAllWithTranslation($locale),
            static fn (object $e): bool => $e instanceof AlchemyFlavors,
        ));
    }

    /**
     * @return list<AlchemyFlavors>
     */
    public function findForCompound(AromaticCompound $compound, string $locale): array
    {
        $qb = $this->createQueryBuilder('f')
            ->innerJoin('f.aromaticsCompounds', 'c')
            ->andWhere('c = :compound')
            ->andWhere('f.deleted_at IS NULL')
            ->setParameter('compound', $compound)
            ->orderBy('f.name', SortDirection::Ascending);

        if ($locale !== 'fr') {
            $qb->leftJoin('f.translations', 'ft', 'WITH', 'ft.locale = :loc')
                ->addSelect('ft', 'COALESCE(ft.name, f.name) AS HIDDEN sortName')
                ->setParameter('loc', $locale)
                ->orderBy('sortName', SortDirection::Ascending);
        }

        return $qb->getQuery()
            ->getResult();
    }
}
