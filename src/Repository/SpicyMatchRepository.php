<?php

namespace App\Repository;

use App\Entity\SpicyMatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpicyMatch>
 *
 * @method SpicyMatch|null find($id, $lockMode = null, $lockVersion = null)
 * @method SpicyMatch|null findOneBy(array $criteria, array $orderBy = null)
 * @method SpicyMatch[]    findAll()
 * @method SpicyMatch[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpicyMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpicyMatch::class);
    }
}
