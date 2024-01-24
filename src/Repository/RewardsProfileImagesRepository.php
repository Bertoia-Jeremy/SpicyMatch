<?php

namespace App\Repository;

use App\Entity\RewardsProfileImages;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RewardsProfileImages>
 *
 * @method RewardsProfileImages|null find($id, $lockMode = null, $lockVersion = null)
 * @method RewardsProfileImages|null findOneBy(array $criteria, array $orderBy = null)
 * @method RewardsProfileImages[]    findAll()
 * @method RewardsProfileImages[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RewardsProfileImagesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RewardsProfileImages::class);
    }

//    /**
//     * @return RewardsProfileImages[] Returns an array of RewardsProfileImages objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?RewardsProfileImages
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
