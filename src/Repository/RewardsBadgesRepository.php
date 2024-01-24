<?php

namespace App\Repository;

use App\Entity\RewardsBadges;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RewardsBadges>
 *
 * @method RewardsBadges|null find($id, $lockMode = null, $lockVersion = null)
 * @method RewardsBadges|null findOneBy(array $criteria, array $orderBy = null)
 * @method RewardsBadges[]    findAll()
 * @method RewardsBadges[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RewardsBadgesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RewardsBadges::class);
    }

//    /**
//     * @return RewardsBadges[] Returns an array of RewardsBadges objects
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

//    public function findOneBySomeField($value): ?RewardsBadges
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
