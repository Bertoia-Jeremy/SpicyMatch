<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AlchemyFlavors;
use App\Entity\AromaticCompound;
use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Repository\Concern\LocalizedSlugLookupTrait;
use App\Seo\SitemapSourceInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

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

    /**
     * @return list<AromaticCompound>
     */
    public function findForFlavor(AlchemyFlavors $flavor, string $locale): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere(':flavor MEMBER OF c.alchemyFlavors')
            ->andWhere('c.deleted_at IS NULL')
            ->setParameter('flavor', $flavor);

        if ($locale === 'fr') {
            $qb->orderBy('c.name', SortDirection::Ascending);
        } else {
            $qb->leftJoin('c.translations', 'ct', 'WITH', 'ct.locale = :loc')
                ->addSelect('ct', 'COALESCE(ct.name, c.name) AS HIDDEN sortName')
                ->setParameter('loc', $locale)
                ->orderBy('sortName', SortDirection::Ascending);
        }

        return array_values(array_filter(
            $qb->getQuery()
                ->getResult(),
            static fn (mixed $c): bool => $c instanceof AromaticCompound,
        ));
    }

    /**
     * @return list<array{compound: AromaticCompound, carriers: int}>
     */
    public function findSignatureForGroup(AromaticGroups $group, string $locale, int $limit = 6): array
    {
        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c.id AS id', 'COUNT(DISTINCT s.id) AS carriers')
            ->from(Spices::class, 's')
            ->innerJoin('s.aromaticsCompounds', 'c')
            ->andWhere('s.aromaticGroups = :group')
            ->andWhere('s.deleted_at IS NULL')
            ->andWhere('c.deleted_at IS NULL')
            ->setParameter('group', $group)
            ->groupBy('c.id', 'c.name')
            ->orderBy('carriers', SortDirection::Descending)
            ->addOrderBy('c.name', SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        if ($rows === []) {
            return [];
        }

        $carriers = [];
        foreach ($rows as $row) {
            $carriers[(int) $row['id']] = (int) $row['carriers'];
        }

        $qb = $this->createQueryBuilder('c')
            ->addSelect('f')
            ->leftJoin('c.alchemyFlavors', 'f')
            ->andWhere('c.id IN (:ids)')
            ->setParameter('ids', array_keys($carriers));

        if ($locale !== 'fr') {
            $qb->leftJoin('c.translations', 'ct', 'WITH', 'ct.locale = :loc')
                ->leftJoin('f.translations', 'ft', 'WITH', 'ft.locale = :loc')
                ->addSelect('ct', 'ft')
                ->setParameter('loc', $locale);
        }

        $byId = [];
        foreach ($qb->getQuery()->getResult() as $compound) {
            if ($compound instanceof AromaticCompound) {
                $byId[$compound->getId()] = $compound;
            }
        }

        $signature = [];
        foreach ($carriers as $id => $count) {
            if (isset($byId[$id])) {
                $signature[] = [
                    'compound' => $byId[$id],
                    'carriers' => $count,
                ];
            }
        }

        return $signature;
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
