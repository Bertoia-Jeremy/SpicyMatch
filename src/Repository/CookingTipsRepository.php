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

//    /**
//     * @return CookingTips[] Returns an array of CookingTips objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?CookingTips
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
