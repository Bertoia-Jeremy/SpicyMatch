<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PendingGamificationNotification;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PendingGamificationNotification>
 */
class PendingGamificationNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PendingGamificationNotification::class);
    }

    /**
     * @return PendingGamificationNotification[]
     */
    public function findUndeliveredForUser(Users $user): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.deliveredAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
