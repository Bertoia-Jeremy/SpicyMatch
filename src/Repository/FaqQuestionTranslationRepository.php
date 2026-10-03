<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FaqQuestionTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaqQuestionTranslation>
 */
class FaqQuestionTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaqQuestionTranslation::class);
    }
}
