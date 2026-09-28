<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameSession;
use App\Entity\Users;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

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

        if ($mode instanceof GameMode) {
            $qb->andWhere('gs.gameMode = :mode')
                ->setParameter('mode', $mode->value);
        }

        return (int) $qb->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int> Keyed by GameMode::value
     */
    public function countTodayByUserGrouped(Users $user): array
    {
        $rows = $this->createQueryBuilder('gs')
            ->select('gs.gameMode, COUNT(gs.id) AS cnt')
            ->where('gs.user = :user')
            ->andWhere('gs.startedAt >= :today')
            ->groupBy('gs.gameMode')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $mode = $row['gameMode'] instanceof GameMode ? $row['gameMode']->value : (string) $row['gameMode'];
            $result[$mode] = (int) $row['cnt'];
        }

        return $result;
    }

    /**
     * @return array<string, int> Keyed by GameMode::value
     */
    public function findBestScoreByUserGrouped(Users $user): array
    {
        $rows = $this->createQueryBuilder('gs')
            ->select('gs.gameMode, MAX(gs.score) AS best')
            ->where('gs.user = :user')
            ->andWhere('gs.finishedAt IS NOT NULL')
            ->groupBy('gs.gameMode')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $mode = $row['gameMode'] instanceof GameMode ? $row['gameMode']->value : (string) $row['gameMode'];
            $result[$mode] = (int) $row['best'];
        }

        return $result;
    }

    /**
     * @return list<float>
     */
    public function findRecentAccuracies(
        Users $user,
        GameMode $mode,
        GameDifficulty $difficulty,
        int $limit,
    ): array {
        $rows = $this->createQueryBuilder('gs')
            ->select('gs.correctAnswers AS ok', 'gs.totalQuestions AS total')
            ->where('gs.user = :user')
            ->andWhere('gs.gameMode = :mode')
            ->andWhere('gs.difficulty = :difficulty')
            ->andWhere('gs.finishedAt IS NOT NULL')
            ->andWhere('gs.totalQuestions > 0')
            ->setParameter('user', $user)
            ->setParameter('mode', $mode->value)
            ->setParameter('difficulty', $difficulty->value)
            ->orderBy('gs.finishedAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(
            static fn (array $row): float => round((int) $row['ok'] / (int) $row['total'] * 100, 1),
            $rows,
        ));
    }

    /**
     * @return GameSession[]
     */
    public function findByUser(Users $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('gs')
            ->where('gs.user = :user')
            ->setParameter('user', $user)
            ->orderBy('gs.startedAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int>
     */
    public function sumFinishedScoreGroupedByUser(): array
    {
        $rows = $this->createQueryBuilder('gs')
            ->select('IDENTITY(gs.user) AS uid', 'SUM(gs.score) AS total')
            ->where('gs.finishedAt IS NOT NULL')
            ->andWhere('gs.user IS NOT NULL')
            ->groupBy('gs.user')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['uid']] = (int) $row['total'];
        }

        return $result;
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

    /**
     * @return Query<null, mixed>
     */
    public function findByUserQuery(Users $user): Query
    {
        return $this->createQueryBuilder('gs')
            ->where('gs.user = :user')
            ->setParameter('user', $user)
            ->orderBy('gs.startedAt', SortDirection::Descending)
            ->getQuery();
    }

    public function countPerfectRunsByMode(Users $user, GameMode $mode): int
    {
        return (int) $this->createQueryBuilder('gs')
            ->select('COUNT(gs.id)')
            ->where('gs.user = :user')
            ->andWhere('gs.gameMode = :mode')
            ->andWhere('gs.finishedAt IS NOT NULL')
            ->andWhere('gs.correctAnswers = gs.totalQuestions')
            ->andWhere('gs.totalQuestions > 0')
            ->setParameter('user', $user)
            ->setParameter('mode', $mode->value)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
