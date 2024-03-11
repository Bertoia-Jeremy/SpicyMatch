<?php

namespace App\Repository;

use App\Entity\PreparationTips;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreparationTips>
 *
 * @method PreparationTips|null find($id, $lockMode = null, $lockVersion = null)
 * @method PreparationTips|null findOneBy(array $criteria, array $orderBy = null)
 * @method PreparationTips[]    findAll()
 * @method PreparationTips[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PreparationTipsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreparationTips::class);
    }
}
