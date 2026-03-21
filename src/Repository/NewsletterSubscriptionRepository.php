<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewsletterSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscription>
 */
class NewsletterSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscription::class);
    }

    public function findActiveByEmail(string $email): ?NewsletterSubscription
    {
        return $this->createQueryBuilder('ns')
            ->where('ns.email = :email')
            ->andWhere('ns.unsubscribedAt IS NULL')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByEmail(string $email): ?NewsletterSubscription
    {
        return $this->findOneBy([
            'email' => $email,
        ]);
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('ns')
            ->select('COUNT(ns.id)')
            ->where('ns.unsubscribedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
