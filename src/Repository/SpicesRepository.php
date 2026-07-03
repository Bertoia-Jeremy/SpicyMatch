<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Spices;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Spices>
 */
class SpicesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Spices::class);
    }

    public function findOneByLocalizedSlug(string $slug, string $locale): ?Spices
    {
        if ('fr' !== $locale) {
            $translated = $this->createQueryBuilder('e')
                ->innerJoin('e.translations', 't', 'WITH', 't.locale = :loc AND t.slug = :slug')
                ->setParameter('loc', $locale)
                ->setParameter('slug', $slug)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (null !== $translated) {
                return $translated;
            }
        }

        return $this->findOneBy([
            'slug' => $slug,
        ]);
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

    /**
     * @return list<Spices>
     */
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
            ->select('s.id', 's.name', 's.slug', 's.description', 's.file', 'ag.color', 'ag.name AS groupName')
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
            ->select(
                's.id',
                's.name',
                's.slug',
                's.description',
                's.file',
                'ag.id AS agId',
                'ag.color',
                'ag.name AS groupName',
                'st.id AS stId',
                'st.name AS typeName'
            )
            ->leftJoin('s.aromaticGroups', 'ag')
            ->leftJoin('s.spicyType', 'st')
            ->orderBy('ag.name')
            ->addOrderBy('s.name')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * Enrichissement batch pour le moteur OAV : charge les données d'affichage
     * d'un ensemble d'IDs en une seule requête (pas de N+1 sur relations).
     *
     * Utilisé par CompatibleSpiceFinder après un appel à MatchPipeline::run().
     * Nom épice + groupe localisés (COALESCE FR) si $locale ≠ fr ; le type n'est pas traduisible.
     *
     * @param list<int>   $ids
     * @param string|null $locale null ou 'fr' → noms canoniques directs
     *
     * @return list<array{id: int, name: string, slug: ?string, file: ?string, agId: ?int, color: ?string, groupName: ?string, stId: ?int, typeName: ?string}>
     */
    public function findEnrichedByIds(array $ids, ?string $locale = null): array
    {
        if ([] === $ids) {
            return [];
        }

        if (null === $locale || 'fr' === $locale) {
            return $this->createQueryBuilder('s')
                ->select(
                    's.id',
                    's.name',
                    's.slug',
                    's.file',
                    'ag.id AS agId',
                    'ag.color',
                    'ag.name AS groupName',
                    'st.id AS stId',
                    'st.name AS typeName'
                )
                ->leftJoin('s.aromaticGroups', 'ag')
                ->leftJoin('s.spicyType', 'st')
                ->where('s.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getArrayResult()
            ;
        }

        return $this->createQueryBuilder('s')
            ->select(
                's.id',
                'COALESCE(str.name, s.name) AS name',
                'COALESCE(str.slug, s.slug) AS slug',
                's.file',
                'ag.id AS agId',
                'ag.color',
                'COALESCE(agt.name, ag.name) AS groupName',
                'st.id AS stId',
                'st.name AS typeName'
            )
            ->leftJoin('s.aromaticGroups', 'ag')
            ->leftJoin('s.spicyType', 'st')
            ->leftJoin('s.translations', 'str', 'WITH', 'str.locale = :loc')
            ->leftJoin('ag.translations', 'agt', 'WITH', 'agt.locale = :loc')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->setParameter('loc', $locale)
            ->getQuery()
            ->getArrayResult()
        ;
    }

    /**
     * @return list<array{id: int, name: string, slug: ?string, type: string}>
     */
    public function search(string $word, ?string $locale = null): array
    {
        $word = mb_substr(trim($word), 0, 100);
        if ('' === $word) {
            return [];
        }

        $like = '%'.$word.'%';
        $conn = $this->getEntityManager()
            ->getConnection();

        if (null === $locale || 'fr' === $locale) {
            $sql = "SELECT s.id, s.name, s.slug, 'spice' AS `type`
                    FROM spices s
                    WHERE s.name LIKE ? AND s.deleted_at IS NULL
                    UNION
                    SELECT ac.id, ac.name, ac.slug, 'aromatic_compound' AS `type`
                    FROM aromatic_compound ac
                    WHERE ac.name LIKE ? AND ac.deleted_at IS NULL
                    UNION
                    SELECT ag.id, ag.name, ag.slug, 'aromatic_group' AS `type`
                    FROM aromatic_groups ag
                    WHERE ag.name LIKE ? AND ag.deleted_at IS NULL
                    UNION
                    SELECT af.id, af.name, af.slug, 'alchemy_flavor' AS `type`
                    FROM alchemy_flavors af
                    WHERE af.name LIKE ? AND af.deleted_at IS NULL
                    UNION
                    SELECT pm.id, pm.name, pm.slug, 'preparation_method' AS `type`
                    FROM preparation_methods pm
                    WHERE pm.name LIKE ? AND pm.deleted_at IS NULL
                    ORDER BY `name`
                    LIMIT 20";

            return $conn->executeQuery($sql, [$like, $like, $like, $like, $like])
                ->fetchAllAssociative();
        }

        $sql = "SELECT s.id, COALESCE(st.name, s.name) AS name, COALESCE(st.slug, s.slug) AS slug, 'spice' AS `type`
                FROM spices s
                LEFT JOIN spice_translation st
                    ON st.spice_id = s.id AND st.locale = ?
                WHERE (s.name LIKE ? OR st.name LIKE ?) AND s.deleted_at IS NULL
                UNION
                SELECT ac.id, COALESCE(act.name, ac.name) AS name, COALESCE(act.slug, ac.slug) AS slug, 'aromatic_compound' AS `type`
                FROM aromatic_compound ac
                LEFT JOIN aromatic_compound_translation act
                    ON act.aromatic_compound_id = ac.id AND act.locale = ?
                WHERE (ac.name LIKE ? OR act.name LIKE ?) AND ac.deleted_at IS NULL
                UNION
                SELECT ag.id, COALESCE(agt.name, ag.name) AS name, COALESCE(agt.slug, ag.slug) AS slug, 'aromatic_group' AS `type`
                FROM aromatic_groups ag
                LEFT JOIN aromatic_groups_translation agt
                    ON agt.aromatic_groups_id = ag.id AND agt.locale = ?
                WHERE (ag.name LIKE ? OR agt.name LIKE ?) AND ag.deleted_at IS NULL
                UNION
                SELECT af.id, COALESCE(aft.name, af.name) AS name, COALESCE(aft.slug, af.slug) AS slug, 'alchemy_flavor' AS `type`
                FROM alchemy_flavors af
                LEFT JOIN alchemy_flavors_translation aft
                    ON aft.alchemy_flavors_id = af.id AND aft.locale = ?
                WHERE (af.name LIKE ? OR aft.name LIKE ?) AND af.deleted_at IS NULL
                UNION
                SELECT pm.id, COALESCE(pmt.name, pm.name) AS name, COALESCE(pmt.slug, pm.slug) AS slug, 'preparation_method' AS `type`
                FROM preparation_methods pm
                LEFT JOIN preparation_methods_translation pmt
                    ON pmt.preparation_methods_id = pm.id AND pmt.locale = ?
                WHERE (pm.name LIKE ? OR pmt.name LIKE ?) AND pm.deleted_at IS NULL
                ORDER BY `name`
                LIMIT 20";

        return $conn->executeQuery($sql, [
            $locale, $like, $like,
            $locale, $like, $like,
            $locale, $like, $like,
            $locale, $like, $like,
            $locale, $like, $like,
        ])->fetchAllAssociative();
    }

    /**
     * Filter spices by aromatic group, spicy type and/or name prefix.
     * Eager-loads aromaticGroups and spicyType to prevent N+1 in templates.
     * Passing all nulls returns all non-deleted spices (replaces findAll() on the catalog page).
     *
     * @return list<Spices>
     */
    public function findFiltered(?int $aromaticGroupId, ?int $spicyTypeId, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('ag', 'st')
            ->leftJoin('s.aromaticGroups', 'ag')
            ->leftJoin('s.spicyType', 'st')
            ->orderBy('s.name', 'ASC');

        if (null !== $aromaticGroupId) {
            $qb->andWhere('s.aromaticGroups = :agId')
                ->setParameter('agId', $aromaticGroupId);
        }

        if (null !== $spicyTypeId) {
            $qb->andWhere('s.spicyType = :stId')
                ->setParameter('stId', $spicyTypeId);
        }

        if (null !== $search && '' !== $search) {
            $qb->andWhere('s.name LIKE :search')
                ->setParameter('search', $search.'%');
        }

        return $qb->getQuery()
            ->getResult();
    }

    /**
     * Load candidate spices for compatibility scoring.
     *
     * Returns Spices that have at least one of the given shared compound IDs
     * (main or secondary), excluding already-selected spice IDs.
     * Compounds and AlchemyFlavors are eagerly loaded to avoid N+1 during scoring.
     *
     * @param list<int> $sharedCompoundIds
     * @param list<int> $excludedSpiceIds
     *
     * @return list<Spices>
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

        // Step 2: Load with compound relations eagerly to avoid N+1.
        // AlchemyFlavors are NOT loaded — they are excluded from scoring.
        return $this->createQueryBuilder('s')
            ->addSelect('mainAc', 'secAc', 'ag', 'st')
            ->leftJoin('s.aromaticsCompounds', 'mainAc')
            ->leftJoin('s.secondary_aromatics_compounds', 'secAc')
            ->leftJoin('s.aromaticGroups', 'ag')
            ->leftJoin('s.spicyType', 'st')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $candidateIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find spices that share ZERO aromatic compounds (main or secondary) with the given spice.
     *
     * Uses NOT EXISTS subqueries for efficiency — no PHP scoring needed.
     *
     * @param list<int> $excludeIds
     *
     * @return list<Spices>
     */
    public function findIncompatibleWith(Spices $spice, array $excludeIds = []): array
    {
        $sql = '
            SELECT s.id
            FROM spices s
            WHERE s.id != :spiceId
                AND s.deleted_at IS NULL
                AND NOT EXISTS (
                    SELECT 1
                    FROM spices_aromatic_compound sac_base
                    JOIN spices_aromatic_compound sac_candidate
                        ON sac_candidate.aromatic_compound_id = sac_base.aromatic_compound_id
                    WHERE sac_base.spices_id = :spiceId
                        AND sac_candidate.spices_id = s.id
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM spices_aromatic_compound sac_base2
                    JOIN secondary_spices_aromatic_compound ssac_candidate
                        ON ssac_candidate.aromatic_compound_id = sac_base2.aromatic_compound_id
                    WHERE sac_base2.spices_id = :spiceId
                        AND ssac_candidate.spices_id = s.id
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM secondary_spices_aromatic_compound ssac_base
                    JOIN spices_aromatic_compound sac_candidate2
                        ON sac_candidate2.aromatic_compound_id = ssac_base.aromatic_compound_id
                    WHERE ssac_base.spices_id = :spiceId
                        AND sac_candidate2.spices_id = s.id
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM secondary_spices_aromatic_compound ssac_base2
                    JOIN secondary_spices_aromatic_compound ssac_candidate2
                        ON ssac_candidate2.aromatic_compound_id = ssac_base2.aromatic_compound_id
                    WHERE ssac_base2.spices_id = :spiceId
                        AND ssac_candidate2.spices_id = s.id
                )
        ';

        $params = [
            'spiceId' => $spice->getId(),
        ];
        $types = [
            'spiceId' => ParameterType::INTEGER,
        ];

        if (! empty($excludeIds)) {
            $sql .= ' AND s.id NOT IN (:excludeIds)';
            $params['excludeIds'] = $excludeIds;
        }

        $conn = $this->getEntityManager()
            ->getConnection();
        $ids = $conn->executeQuery($sql, $params, $types)
            ->fetchFirstColumn();

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->addSelect('ag')
            ->leftJoin('s.aromaticGroups', 'ag')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $ids)
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

        $stmt = $this->getEntityManager()
            ->getConnection()
            ->prepare($sql);
        $stmt->bindValue('limit', $limit, ParameterType::INTEGER);

        return $stmt->executeQuery()
            ->fetchAllAssociative();
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

        $stmt = $this->getEntityManager()
            ->getConnection()
            ->prepare($sql);
        $stmt->bindValue('limit', $limit, ParameterType::INTEGER);

        return $stmt->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<Spices>
     */
    public function findRelated(Spices $spice, int $limit = 4): array
    {
        $group = $spice->getAromaticGroups();
        if (null === $group) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->where('s.aromaticGroups = :group')
            ->andWhere('s.id != :id')
            ->setParameter('group', $group)
            ->setParameter('id', $spice->getId())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne un mapping id → name (localisé) pour une liste d'IDs.
     *
     * Hydratation BATCH (i18n) : un seul LEFT JOIN filtré par locale + COALESCE
     * vers le FR canonique. Zéro N+1 — c'est le SEUL point i18n du hot-path du
     * moteur OAV (le pipeline lui-même reste agnostique de la langue). Requête
     * DQL scalaire (pas d'hydratation entité).
     *
     * @param int[]       $ids
     * @param string|null $locale locale cible ; null ou 'fr' → noms canoniques directs
     *
     * @return array<int, string> spice_id => name
     */
    public function findNamesById(array $ids, ?string $locale = null): array
    {
        if ([] === $ids) {
            return [];
        }

        // FR (défaut) : pas de JOIN, le nom canonique vit sur l'entité.
        if (null === $locale || 'fr' === $locale) {
            $rows = $this->createQueryBuilder('s')
                ->select('s.id', 's.name')
                ->where('s.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getArrayResult();

            /** @var array<int, string> */
            return array_column($rows, 'name', 'id');
        }

        $rows = $this->createQueryBuilder('s')
            ->select('s.id AS id', 'COALESCE(t.name, s.name) AS name')
            ->leftJoin('s.translations', 't', 'WITH', 't.locale = :loc')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->setParameter('loc', $locale)
            ->getQuery()
            ->getArrayResult();

        /** @var array<int, string> */
        return array_column($rows, 'name', 'id');
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
