<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameSession;
use App\Entity\Users;
use App\Enum\GameMode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameSession>
 */
class GameSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameSession::class);
    }

    public function countTodayByUser(Users $user, ?GameMode $mode = null): int
    {
        $qb = $this->createQueryBuilder('gs')
            ->select('COUNT(gs.id)')
            ->where('gs.user = :user')
            ->andWhere('gs.startedAt >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'));

        if ($mode !== null) {
            $qb->andWhere('gs.gameMode = :mode')
                ->setParameter('mode', $mode->value);
        }

        return (int) $qb->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return GameSession[]
     */
    public function findByUser(Users $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('gs')
            ->where('gs.user = :user')
            ->setParameter('user', $user)
            ->orderBy('gs.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countFinishedByUser(Users $user): int
    {
        return (int) $this->createQueryBuilder('gs')
            ->select('COUNT(gs.id)')
            ->where('gs.user = :user')
            ->andWhere('gs.finishedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByUserQuery(Users $user): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('gs')
            ->where('gs.user = :user')
            ->setParameter('user', $user)
            ->orderBy('gs.startedAt', 'DESC')
            ->getQuery();
    }
}
