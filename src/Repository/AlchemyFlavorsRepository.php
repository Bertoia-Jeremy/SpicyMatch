<?php

namespace App\Repository;

use App\Entity\AlchemyFlavors;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlchemyFlavors>
 *
 * @method AlchemyFlavors|null find($id, $lockMode = null, $lockVersion = null)
 * @method AlchemyFlavors|null findOneBy(array $criteria, array $orderBy = null)
 * @method AlchemyFlavors[]    findAll()
 * @method AlchemyFlavors[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlchemyFlavorsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlchemyFlavors::class);
    }

    public function add(AlchemyFlavors $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AlchemyFlavors $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return AlchemyFlavors[] Returns an array of AlchemyFlavors objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('a.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?AlchemyFlavors
//    {
//        return $this->createQueryBuilder('a')
//            ->andWhere('a.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
