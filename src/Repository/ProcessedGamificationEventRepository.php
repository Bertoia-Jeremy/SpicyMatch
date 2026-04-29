<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProcessedGamificationEvent;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessedGamificationEvent>
 */
class ProcessedGamificationEventRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($registry, ProcessedGamificationEvent::class);
    }

    /**
     * Atomic claim: returns true if this was the first time the (eventType, eventKey)
     * pair was claimed; false if the pair was already in the ledger.
     *
     * The UNIQUE constraint on (event_type, event_key) ensures that concurrent
     * retries of the same event can never both succeed — at most one wins.
     */
    public function claim(Users $user, string $eventType, string $eventKey): bool
    {
        $record = new ProcessedGamificationEvent($user, $eventType, $eventKey);

        try {
            $this->em->persist($record);
            $this->em->flush();

            return true;
        } catch (UniqueConstraintViolationException) {
            // Already processed — retry detected, short-circuit silently.
            $this->em->clear();

            return false;
        }
    }
}
