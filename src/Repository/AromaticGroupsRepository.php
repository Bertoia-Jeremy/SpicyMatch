<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AromaticGroups;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AromaticGroups>
 */
class AromaticGroupsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AromaticGroups::class);
    }

    public function add(AromaticGroups $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->persist($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function remove(AromaticGroups $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    /**
     * Hydratation batch id → name localisé (LEFT JOIN locale + COALESCE FR) pour
     * éviter le N+1 sur les listes.
     *
     * @param int[]       $ids
     * @param string|null $locale null ou 'fr' → noms canoniques directs
     *
     * @return array<int, string> group_id => name
     */
    public function findNamesById(array $ids, ?string $locale = null): array
    {
        if ($ids === []) {
            return [];
        }

        if ($locale === null || $locale === 'fr') {
            $rows = $this->createQueryBuilder('g')
                ->select('g.id', 'g.name')
                ->where('g.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getArrayResult();

            /** @var array<int, string> */
            return array_column($rows, 'name', 'id');
        }

        $rows = $this->createQueryBuilder('g')
            ->select('g.id AS id', 'COALESCE(t.name, g.name) AS name')
            ->leftJoin('g.translations', 't', 'WITH', 't.locale = :loc')
            ->where('g.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->setParameter('loc', $locale)
            ->getQuery()
            ->getArrayResult();

        /** @var array<int, string> */
        return array_column($rows, 'name', 'id');
    }
}
