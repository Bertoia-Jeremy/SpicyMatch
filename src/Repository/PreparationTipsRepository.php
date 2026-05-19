<?php

namespace App\Repository;

use App\Entity\PreparationTips;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
