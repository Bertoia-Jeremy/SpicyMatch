<?php

namespace App\Repository;

use App\Entity\PreparationMethods;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreparationMethods>
 *
 * @method PreparationMethods|null find($id, $lockMode = null, $lockVersion = null)
 * @method PreparationMethods|null findOneBy(array $criteria, array $orderBy = null)
 * @method PreparationMethods[]    findAll()
 * @method PreparationMethods[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationMethodsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreparationMethods::class);
    }

//    /**
//     * @return PreparationMethods[] Returns an array of PreparationMethods objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?PreparationMethods
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
