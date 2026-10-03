<?php

namespace App\Repository;

use App\Entity\PreparationMethods;
use App\Entity\PreparationTips;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<PreparationTips>
 */
class PreparationTipsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreparationTips::class);
    }

    /**
     * @return list<PreparationTips>
     */
    public function findForMethod(PreparationMethods $method, string $locale): array
    {
        $qb = $this->createQueryBuilder('pt')
            ->addSelect('s', 'ag')
            ->innerJoin('pt.spice', 's')
            ->leftJoin('s.aromaticGroups', 'ag')
            ->andWhere('pt.preparationMethod = :method')
            ->andWhere('pt.deleted_at IS NULL')
            ->andWhere('s.deleted_at IS NULL')
            ->setParameter('method', $method);

        if ($locale === 'fr') {
            $qb->orderBy('s.name', SortDirection::Ascending);
        } else {
            $qb->leftJoin('pt.translations', 'ptt', 'WITH', 'ptt.locale = :loc')
                ->leftJoin('s.translations', 'str', 'WITH', 'str.locale = :loc')
                ->addSelect('ptt', 'str', 'COALESCE(str.name, s.name) AS HIDDEN sortName')
                ->setParameter('loc', $locale)
                ->orderBy('sortName', SortDirection::Ascending);
        }

        return $qb->getQuery()
            ->getResult();
    }

    /**
     * @return list<PreparationTips>
     */
    public function findAllByStringIds(string $stringIds): array
    {
        $arrayIds = explode(',', $stringIds);

        return $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $arrayIds)
            ->orderBy('p.spice')
            ->getQuery()
            ->getResult()
        ;
    }
}
