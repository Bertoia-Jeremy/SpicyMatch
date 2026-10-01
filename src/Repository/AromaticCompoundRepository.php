<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AromaticCompound;
use App\Repository\Concern\LocalizedSlugLookupTrait;
use App\Seo\SitemapSourceInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AromaticCompound>
 */
class AromaticCompoundRepository extends ServiceEntityRepository implements SitemapSourceInterface
{
    use LocalizedSlugLookupTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AromaticCompound::class);
    }

    public function findOneByLocalizedSlug(string $slug, string $locale): ?AromaticCompound
    {
        $entity = $this->lookupByLocalizedSlug($slug, $locale);

        return $entity instanceof AromaticCompound ? $entity : null;
    }

    /**
     * @return list<AromaticCompound>
     */
    public function findAllForLocale(string $locale): array
    {
        return array_values(array_filter(
            $this->findAllWithTranslation($locale),
            static fn (object $e): bool => $e instanceof AromaticCompound,
        ));
    }

    public function add(AromaticCompound $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->persist($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function remove(AromaticCompound $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param int[]       $ids
     * @param string|null $locale null ou 'fr' → noms canoniques directs
     * @return array<int, string> compound_id => name
     */
    public function findNamesById(array $ids, ?string $locale = null): array
    {
        if ($ids === []) {
            return [];
        }

        if ($locale === null || $locale === 'fr') {
            $rows = $this->createQueryBuilder('a')
                ->select('a.id', 'a.name')
                ->where('a.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getArrayResult();

            /** @var array<int, string> */
            return array_column($rows, 'name', 'id');
        }

        $rows = $this->createQueryBuilder('a')
            ->select('a.id AS id', 'COALESCE(t.name, a.name) AS name')
            ->leftJoin('a.translations', 't', 'WITH', 't.locale = :loc')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->setParameter('loc', $locale)
            ->getQuery()
            ->getArrayResult();

        /** @var array<int, string> */
        return array_column($rows, 'name', 'id');
    }
}
