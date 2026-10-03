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

    /**
     * @return array{xp: int, processed: bool}
     */
    public function findXpSnapshot(Users $user, string $eventType, string $eventKey): array
    {
        $row = $this->getEntityManager()
            ->getConnection()
            ->fetchAssociative(
                'SELECT COALESCE((SELECT up.xp FROM user_progression up WHERE up.user_id = ?), 0) AS xp,
                    EXISTS(SELECT 1 FROM processed_gamification_event e WHERE e.event_type = ? AND e.event_key = ?) AS processed',
                [$user->getId(), $eventType, $eventKey],
                [ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING],
            );

        return [
            'xp' => max(0, (int) ($row['xp'] ?? 0)),
            'processed' => (bool) ($row['processed'] ?? false),
        ];
    }
}
