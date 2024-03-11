<?php

namespace App\Repository;

use App\Entity\SpicyMatchHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpicyMatchHistory>
 *
 * @method SpicyMatchHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method SpicyMatchHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method SpicyMatchHistory[]    findAll()
 * @method SpicyMatchHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpicyMatchHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpicyMatchHistory::class);
    }
}
