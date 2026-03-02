<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Discovery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Discovery>
 */
class DiscoveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Discovery::class);
    }

    public function findByHash(string $hash): ?Discovery
    {
        return $this->findOneBy(['combinationHash' => $hash]);
    }
}
