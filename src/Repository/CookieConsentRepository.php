<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CookieConsent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CookieConsent>
 */
class CookieConsentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CookieConsent::class);
    }

    public function purgeExpired(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('cc')
            ->delete()
            ->where('cc.consentedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
