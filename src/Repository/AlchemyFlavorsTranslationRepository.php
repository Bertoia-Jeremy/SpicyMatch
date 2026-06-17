<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AlchemyFlavorsTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlchemyFlavorsTranslation>
 */
class AlchemyFlavorsTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlchemyFlavorsTranslation::class);
    }
}
