<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AromaticCompoundTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AromaticCompoundTranslation>
 */
class AromaticCompoundTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AromaticCompoundTranslation::class);
    }
}
