<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProcessedGamificationEvent;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessedGamificationEvent>
 */
class ProcessedGamificationEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessedGamificationEvent::class);
    }

    public function claim(Users $user, string $eventType, string $eventKey): bool
    {
        $connection = $this->getEntityManager()
            ->getConnection();

        try {
            $connection->insert(
                'processed_gamification_event',
                [
                    'event_type' => $eventType,
                    'event_key' => $eventKey,
                    'user_id' => $user->getId(),
                    'processed_at' => new \DateTimeImmutable(),
                ],
                [
                    'event_type' => ParameterType::STRING,
                    'event_key' => ParameterType::STRING,
                    'user_id' => ParameterType::INTEGER,
                    'processed_at' => Types::DATETIME_IMMUTABLE,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }
}
