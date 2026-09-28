<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpiceDuo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<SpiceDuo>
 */
class SpiceDuoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpiceDuo::class);
    }

    /**
     * @param list<int> $spiceIds
     * @return list<array{spiceId: int, prepId: int, cookId: int, rank: int, prepTitle: string|null, title: string, effect: string, science: string, example: string}>
     */
    public function findBySpiceIds(array $spiceIds, string $locale = 'fr'): array
    {
        if ($spiceIds === []) {
            return [];
        }

        return $this->fetchRows(
            $this->baseQuery($locale)
                ->where('IDENTITY(pt.spice) IN (:spiceIds)')
                ->setParameter('spiceIds', $spiceIds)
        );
    }

    /**
     * @param list<int> $prepTipIds
     * @param list<int> $cookTipIds
     * @return list<array{spiceId: int, prepId: int, cookId: int, rank: int, prepTitle: string|null, title: string, effect: string, science: string, example: string}>
     */
    public function findByTipIds(array $prepTipIds, array $cookTipIds, string $locale = 'fr'): array
    {
        if ($prepTipIds === [] || $cookTipIds === []) {
            return [];
        }

        return $this->fetchRows(
            $this->baseQuery($locale)
                ->where('pt.id IN (:prepIds)')
                ->andWhere('ct.id IN (:cookIds)')
                ->setParameter('prepIds', $prepTipIds)
                ->setParameter('cookIds', $cookTipIds)
        );
    }

    private function baseQuery(string $locale): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d')
            ->select(
                'IDENTITY(pt.spice) AS spiceId',
                'pt.id AS prepId',
                'ct.id AS cookId',
                'd.rank AS rank'
            )
            ->innerJoin('d.preparationTip', 'pt')
            ->innerJoin('d.cookingTip', 'ct')
            ->orderBy('d.rank', SortDirection::Ascending)
            ->addOrderBy('d.id', SortDirection::Ascending);

        if ($locale === 'fr') {
            return $qb->addSelect('pt.title AS prepTitle', 'd.title AS title', 'd.effect AS effect', 'd.science AS science', 'd.example AS example');
        }

        return $qb->leftJoin('d.translations', 't', 'WITH', 't.locale = :locale')
            ->leftJoin('pt.translations', 'ptt', 'WITH', 'ptt.locale = :locale')
            ->addSelect(
                'COALESCE(ptt.title, pt.title) AS prepTitle',
                'COALESCE(t.title, d.title) AS title',
                'COALESCE(t.effect, d.effect) AS effect',
                'COALESCE(t.science, d.science) AS science',
                'COALESCE(t.example, d.example) AS example'
            )
            ->setParameter('locale', $locale);
    }

    /**
     * @return list<array{spiceId: int, prepId: int, cookId: int, rank: int, prepTitle: string|null, title: string, effect: string, science: string, example: string}>
     */
    private function fetchRows(QueryBuilder $qb): array
    {
        /** @var list<array{spiceId: int|string, prepId: int, cookId: int, rank: int, prepTitle: string|null, title: string, effect: string, science: string, example: string}> $rows */
        $rows = $qb->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $r): array => [
            ...$r,
            'spiceId' => (int) $r['spiceId'],
        ], $rows);
    }
}
