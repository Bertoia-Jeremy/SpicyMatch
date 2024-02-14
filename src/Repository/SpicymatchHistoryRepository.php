<?php

namespace App\Repository;

use App\Entity\SpicymatchHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpicymatchHistory>
 *
 * @method SpicymatchHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method SpicymatchHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method SpicymatchHistory[]    findAll()
 * @method SpicymatchHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpicymatchHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpicymatchHistory::class);
    }
}
