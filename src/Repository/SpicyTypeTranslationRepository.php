<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpicyTypeTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpicyTypeTranslation>
 */
class SpicyTypeTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpicyTypeTranslation::class);
    }
}
