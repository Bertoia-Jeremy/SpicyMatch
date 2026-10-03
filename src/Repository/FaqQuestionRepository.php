<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FaqQuestion;
use App\Entity\Spices;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<FaqQuestion>
 */
class FaqQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaqQuestion::class);
    }

    /**
     * @return list<FaqQuestion>
     */
    public function findPublished(string $locale): array
    {
        /** @var list<FaqQuestion> */
        return $this->publishedQuery($locale)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<FaqQuestion>
     */
    public function findPublishedForSpice(Spices $spice, string $locale): array
    {
        /** @var list<FaqQuestion> */
        return $this->publishedQuery($locale)
            ->andWhere(':spice MEMBER OF q.spices')
            ->setParameter('spice', $spice)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<int>
     */
    public function findCategoryIdsInUse(): array
    {
        /** @var list<int> */
        return array_map(intval(...), $this->createQueryBuilder('q')
            ->select('DISTINCT IDENTITY(q.category)')
            ->getQuery()
            ->getSingleColumnResult());
    }

    private function publishedQuery(string $locale): QueryBuilder
    {
        $qb = $this->createQueryBuilder('q')
            ->addSelect('c')
            ->innerJoin('q.category', 'c')
            ->andWhere('q.published = true')
            ->orderBy('c.position', SortDirection::Ascending)
            ->addOrderBy('c.id', SortDirection::Ascending)
            ->addOrderBy('q.position', SortDirection::Ascending)
            ->addOrderBy('q.id', SortDirection::Ascending);

        if ($locale !== 'fr') {
            $qb->addSelect('qt', 'ct')
                ->leftJoin('q.translations', 'qt', 'WITH', 'qt.locale = :loc')
                ->leftJoin('c.translations', 'ct', 'WITH', 'ct.locale = :loc')
                ->setParameter('loc', $locale);
        }

        return $qb;
    }
}
