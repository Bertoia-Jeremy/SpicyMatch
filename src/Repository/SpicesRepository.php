<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Spices;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
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
            ->select('s.id', 's.name', 's.description', 's.file', 'ag.id AS agId', 'ag.color', 'ag.name AS groupName', 'st.id AS stId')
            ->leftJoin('s.aromaticGroups', 'ag')
            ->leftJoin('s.spicyType', 'st')
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
                SELECT ac.id, ac.name, IF(1, "aromatic_compound", "") as `type`
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
    /**
     * Filter spices by aromatic group, spicy type and/or name prefix.
     *
     * @return Spices[]
     */
    public function findFiltered(?int $aromaticGroupId, ?int $spicyTypeId, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->orderBy('s.name', 'ASC');

        if ($aromaticGroupId !== null) {
            $qb->andWhere('s.aromaticGroups = :agId')
                ->setParameter('agId', $aromaticGroupId);
        }

        if ($spicyTypeId !== null) {
            $qb->andWhere('s.spicyType = :stId')
                ->setParameter('stId', $spicyTypeId);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('s.name LIKE :search')
                ->setParameter('search', $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

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

        // Step 2: Load with compound relations eagerly to avoid N+1 in CompatibilityScoreService.
        // AlchemyFlavors are NOT loaded — they are excluded from scoring.
        return $this->createQueryBuilder('s')
            ->addSelect('mainAc', 'secAc', 'ag')
            ->leftJoin('s.aromaticsCompounds', 'mainAc')
            ->leftJoin('s.secondary_aromatics_compounds', 'secAc')
            ->leftJoin('s.aromaticGroups', 'ag')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $candidateIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the top compatible spice pairs based on shared aromatic compounds.
     *
     * Uses raw SQL self-join on pivot tables for performance.
     * Score = sharedMain×3 + sharedSecondary×1 (no group bonus, no alchemy).
     *
     * @return array<array{s1_id: int, s1_name: string, s1_file: ?string, s1_color: ?string, s1_group: ?string, s2_id: int, s2_name: string, s2_file: ?string, s2_color: ?string, s2_group: ?string, score: int}>
     */
    public function findTopCompatiblePairs(int $limit = 20): array
    {
        $sql = '
            SELECT
                s1.id AS s1_id, s1.name AS s1_name, s1.file AS s1_file,
                s2.id AS s2_id, s2.name AS s2_name, s2.file AS s2_file,
                ag1.color AS s1_color, ag1.name AS s1_group,
                ag2.color AS s2_color, ag2.name AS s2_group,
                (COUNT(DISTINCT sac2.aromatic_compound_id) * 3
                 + COUNT(DISTINCT ssac2.aromatic_compound_id)) AS score,
                COUNT(DISTINCT sac2.aromatic_compound_id) AS shared_main,
                COUNT(DISTINCT ssac2.aromatic_compound_id) AS shared_secondary
            FROM spices s1
            JOIN spices s2 ON s2.id > s1.id AND s2.deleted_at IS NULL
            LEFT JOIN aromatic_groups ag1 ON ag1.id = s1.aromaticGroups
            LEFT JOIN aromatic_groups ag2 ON ag2.id = s2.aromaticGroups
            LEFT JOIN spices_aromatic_compound sac1 ON sac1.spices_id = s1.id
            LEFT JOIN spices_aromatic_compound sac2
                ON sac2.aromatic_compound_id = sac1.aromatic_compound_id
                AND sac2.spices_id = s2.id
            LEFT JOIN secondary_spices_aromatic_compound ssac1 ON ssac1.spices_id = s1.id
            LEFT JOIN secondary_spices_aromatic_compound ssac2
                ON ssac2.aromatic_compound_id = ssac1.aromatic_compound_id
                AND ssac2.spices_id = s2.id
            WHERE s1.deleted_at IS NULL
                AND (sac2.spices_id IS NOT NULL OR ssac2.spices_id IS NOT NULL)
            GROUP BY s1.id, s2.id, ag1.color, ag1.name, ag2.color, ag2.name
            ORDER BY score DESC
            LIMIT :limit
        ';

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('limit', $limit, ParameterType::INTEGER);

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * Find the top compatible spice triplets sharing at least one aromatic compound across all 3.
     *
     * Strict intersection: the compound must be present in all 3 spices.
     * Score = sharedMain×3 + sharedSecondary×1.
     *
     * @return array<array{s1_id: int, s1_name: string, s1_file: ?string, s2_id: int, s2_name: string, s2_file: ?string, s3_id: int, s3_name: string, s3_file: ?string, score: int, shared_main: int, shared_secondary: int}>
     */
    public function findTopCompatibleTriplets(int $limit = 10): array
    {
        $sql = '
            SELECT
                s1.id AS s1_id, s1.name AS s1_name, s1.file AS s1_file,
                ag1.color AS s1_color, ag1.name AS s1_group,
                s2.id AS s2_id, s2.name AS s2_name, s2.file AS s2_file,
                ag2.color AS s2_color, ag2.name AS s2_group,
                s3.id AS s3_id, s3.name AS s3_name, s3.file AS s3_file,
                ag3.color AS s3_color, ag3.name AS s3_group,
                COUNT(DISTINCT sac1.aromatic_compound_id) AS shared_main,
                COUNT(DISTINCT ssac1.aromatic_compound_id) AS shared_secondary,
                (COUNT(DISTINCT sac1.aromatic_compound_id) * 3
                 + COUNT(DISTINCT ssac1.aromatic_compound_id)) AS score
            FROM spices s1
            JOIN spices s2 ON s2.id > s1.id AND s2.deleted_at IS NULL
            JOIN spices s3 ON s3.id > s2.id AND s3.deleted_at IS NULL
            LEFT JOIN aromatic_groups ag1 ON ag1.id = s1.aromaticGroups
            LEFT JOIN aromatic_groups ag2 ON ag2.id = s2.aromaticGroups
            LEFT JOIN aromatic_groups ag3 ON ag3.id = s3.aromaticGroups
            LEFT JOIN spices_aromatic_compound sac1 ON sac1.spices_id = s1.id
            LEFT JOIN spices_aromatic_compound sac2
                ON sac2.spices_id = s2.id AND sac2.aromatic_compound_id = sac1.aromatic_compound_id
            LEFT JOIN spices_aromatic_compound sac3
                ON sac3.spices_id = s3.id AND sac3.aromatic_compound_id = sac1.aromatic_compound_id
            LEFT JOIN secondary_spices_aromatic_compound ssac1 ON ssac1.spices_id = s1.id
            LEFT JOIN secondary_spices_aromatic_compound ssac2
                ON ssac2.spices_id = s2.id AND ssac2.aromatic_compound_id = ssac1.aromatic_compound_id
            LEFT JOIN secondary_spices_aromatic_compound ssac3
                ON ssac3.spices_id = s3.id AND ssac3.aromatic_compound_id = ssac1.aromatic_compound_id
            WHERE s1.deleted_at IS NULL
                AND (
                    (sac2.spices_id IS NOT NULL AND sac3.spices_id IS NOT NULL)
                    OR (ssac2.spices_id IS NOT NULL AND ssac3.spices_id IS NOT NULL)
                )
            GROUP BY s1.id, s2.id, s3.id,
                ag1.color, ag1.name, ag2.color, ag2.name, ag3.color, ag3.name
            HAVING score > 0
            ORDER BY score DESC
            LIMIT :limit
        ';

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('limit', $limit, ParameterType::INTEGER);

        return $stmt->executeQuery()->fetchAllAssociative();
    }
}
