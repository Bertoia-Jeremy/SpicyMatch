<?php

namespace App\Repository;

use App\Entity\CookingTips;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CookingTips>
 */
class CookingTipsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookingTips::class);
    }

    /**
     * @return list<CookingTips>
     */
    public function findAllByStringIds(string $stringIds): array
    {
        $arrayIds = explode(',', $stringIds);

        return $this->createQueryBuilder('c')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $arrayIds)
            ->orderBy('c.spice')
            ->getQuery()
            ->getResult()
        ;
    }
}
