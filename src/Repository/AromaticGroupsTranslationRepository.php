<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AromaticGroupsTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AromaticGroupsTranslation>
 */
class AromaticGroupsTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AromaticGroupsTranslation::class);
    }
}
