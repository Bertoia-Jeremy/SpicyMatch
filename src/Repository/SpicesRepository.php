<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Spices;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Spices>
 *
 * @method Spices|null find($id, $lockMode = null, $lockVersion = null)
 * @method Spices|null findOneBy(array $criteria, array $orderBy = null)
 * @method Spices[]    findAll()
 * @method Spices[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpicesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Spices::class);
    }

    public function add(Spices $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->persist($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function remove(Spices $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function findAllByStringIds(string $stringIds): array
    {
        $arrayIds = explode(',', $stringIds);

        return $this->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $arrayIds)
            ->orderBy('s.aromaticGroups')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<array<string>>
     */
    public function findSpicesForMatch(string $idsString): array
    {
        $ids = array_map('intval', explode(',', $idsString));

        return $this->createQueryBuilder('s')
            ->select('s.id', 's.name', 's.description', 's.file', 'ag.color', 'ag.name AS groupName')
            ->leftJoin('s.aromaticGroups', 'ag')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('ag.name')
            ->addOrderBy('s.name')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * @return array<array<string>>
     */
    public function findAllSpices(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.id', 's.name', 's.description', 's.file', 'ag.color', 'ag.name AS groupName')
            ->leftJoin('s.aromaticGroups', 'ag')
            ->orderBy('ag.name')
            ->addOrderBy('s.name')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    public function search(string $word): array
    {
        $sql = 'SELECT s.id, s.name, IF(1, "spice", "") as `type`
                FROM spices s
                WHERE s.name LIKE ?
                    AND deleted_at IS NULL
                UNION
                SELECT ac.id, ac.name, IF(1, "aromatic_coumpound", "") as `type`
                FROM aromatic_compound ac
                WHERE ac.name LIKE ?
                    AND deleted_at IS NULL
                ORDER BY `name`
                LIMIT 10';

        $stmt = $this->getEntityManager()
            ->getConnection()
            ->prepare($sql);
        $stmt->bindValue(1, '%' . $word . '%');
        $stmt->bindValue(2, '%' . $word . '%');

        return $stmt->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Load candidate spices for compatibility scoring.
     *
     * Returns Spices that have at least one of the given shared compound IDs
     * (main or secondary), excluding already-selected spice IDs.
     * Compounds and AlchemyFlavors are eagerly loaded to avoid N+1 during scoring.
     *
     * @param int[] $sharedCompoundIds
     * @param int[] $excludedSpiceIds
     *
     * @return Spices[]
     */
    public function findCandidatesForScoring(array $sharedCompoundIds, array $excludedSpiceIds): array
    {
        if (empty($sharedCompoundIds)) {
            return [];
        }

        // Step 1: Get distinct candidate IDs (spices having ≥1 shared compound)
        $candidateIds = $this->createQueryBuilder('s')
            ->select('s.id')
            ->distinct()
            ->leftJoin('s.aromaticsCompounds', 'mainAc')
            ->leftJoin('s.secondary_aromatics_compounds', 'secAc')
            ->where('mainAc.id IN (:compoundIds) OR secAc.id IN (:compoundIds)')
            ->andWhere('s.id NOT IN (:excludedIds)')
            ->setParameter('compoundIds', $sharedCompoundIds)
            ->setParameter('excludedIds', $excludedSpiceIds ?: [0])
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($candidateIds)) {
            return [];
        }

        // Step 2: Load with full relations (eager to avoid N+1 in CompatibilityScoreService)
        return $this->createQueryBuilder('s')
            ->addSelect('mainAc', 'mainFlavors', 'secAc', 'secFlavors', 'ag')
            ->leftJoin('s.aromaticsCompounds', 'mainAc')
            ->leftJoin('mainAc.alchemyFlavors', 'mainFlavors')
            ->leftJoin('s.secondary_aromatics_compounds', 'secAc')
            ->leftJoin('secAc.alchemyFlavors', 'secFlavors')
            ->leftJoin('s.aromaticGroups', 'ag')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $candidateIds)
            ->getQuery()
            ->getResult();
    }
}
