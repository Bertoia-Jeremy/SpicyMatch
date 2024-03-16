<?php

namespace App\Repository;

use App\Entity\CookingTips;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CookingTips>
 *
 * @method CookingTips|null find($id, $lockMode = null, $lockVersion = null)
 * @method CookingTips|null findOneBy(array $criteria, array $orderBy = null)
 * @method CookingTips[]    findAll()
 * @method CookingTips[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CookingTipsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookingTips::class);
    }

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
