<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CookingTipsTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CookingTipsTranslation>
 */
class CookingTipsTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookingTipsTranslation::class);
    }
}
