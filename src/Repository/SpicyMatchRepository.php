<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpicyMatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpicyMatch>
 */
class SpicyMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpicyMatch::class);
    }

    /**
     * @return list<SpicyMatch>
     */
    public function findGuestMatchesCreatedBefore(\DateTimeImmutable $before, int $limit): array
    {
        return $this->createQueryBuilder('sm')
            ->where('sm.user IS NULL')
            ->andWhere('sm.createdAt < :before')
            ->setParameter('before', $before)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
