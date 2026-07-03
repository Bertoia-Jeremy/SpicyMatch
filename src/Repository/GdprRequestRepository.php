<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GdprRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GdprRequest>
 */
class GdprRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GdprRequest::class);
    }

    public function purgeCreatedBefore(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('g')
            ->delete()
            ->where('g.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
