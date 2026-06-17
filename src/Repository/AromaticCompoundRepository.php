<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AromaticCompound;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AromaticCompound>
 */
class AromaticCompoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AromaticCompound::class);
    }

    public function findOneByLocalizedSlug(string $slug, string $locale): ?AromaticCompound
    {
        if ($locale !== 'fr') {
            $translated = $this->createQueryBuilder('e')
                ->innerJoin('e.translations', 't', 'WITH', 't.locale = :loc AND t.slug = :slug')
                ->setParameter('loc', $locale)
                ->setParameter('slug', $slug)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($translated !== null) {
                return $translated;
            }
        }

        return $this->findOneBy([
            'slug' => $slug,
        ]);
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
     * Hydratation batch id → name localisé (LEFT JOIN locale + COALESCE FR) pour
     * éviter le N+1 sur les listes.
     *
     * @param int[]       $ids
     * @param string|null $locale null ou 'fr' → noms canoniques directs
     *
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
