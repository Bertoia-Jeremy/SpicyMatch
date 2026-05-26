<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompoundPhysical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompoundPhysical>
 */
final class CompoundPhysicalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompoundPhysical::class);
    }
}
