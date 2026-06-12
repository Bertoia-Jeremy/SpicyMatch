<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PreparationMethodsTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreparationMethodsTranslation>
 */
class PreparationMethodsTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreparationMethodsTranslation::class);
    }
}
