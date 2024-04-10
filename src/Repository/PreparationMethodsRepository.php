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
}
