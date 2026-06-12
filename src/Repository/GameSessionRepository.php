<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AromaticGroups;
use App\Entity\GameSession;
use App\Entity\Users;
use App\Enum\GameDifficulty;
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
     * Returns today's session count per mode in a single query.
     * Modes with 0 sessions are absent from the result — callers should use ?? 0.
     *
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

    /**
     * @return \Doctrine\ORM\Query<null, mixed>
     */
    public function findByUserQuery(Users $user): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('gs')
            ->where('gs.user = :user')
            ->setParameter('user', $user)
            ->orderBy('gs.startedAt', 'DESC')
            ->getQuery();
    }

    /**
     * Max score achieved by this user in the given mode, restricted to sessions
     * whose target spice belongs to a specific aromatic group. Drives
     * GAME_SCORE_THRESHOLD achievements with contextAromaticGroup set.
     */
    public function maxScoreInModeForGroup(
        Users $user,
        GameMode $mode,
        AromaticGroups $group,
        ?GameDifficulty $difficulty = null,
    ): int {
        $qb = $this->createQueryBuilder('gs')
            ->select('COALESCE(MAX(gs.score), 0)')
            ->innerJoin('gs.targetSpice', 's')
            ->innerJoin('s.aromaticGroups', 'ag')
            ->where('gs.user = :user')
            ->andWhere('gs.gameMode = :mode')
            ->andWhere('ag = :group')
            ->andWhere('gs.finishedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('mode', $mode->value)
            ->setParameter('group', $group);

        if ($difficulty !== null) {
            $qb->andWhere('gs.difficulty = :difficulty')
                ->setParameter('difficulty', $difficulty->value);
        }

        return (int) $qb->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Number of perfect runs (correctAnswers === totalQuestions) in the given mode.
     */
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

    /**
     * Count of distinct target spices the user has played against across all finished sessions.
     */
    public function countDistinctTargetSpices(Users $user): int
    {
        return (int) $this->createQueryBuilder('gs')
            ->select('COUNT(DISTINCT gs.targetSpice)')
            ->where('gs.user = :user')
            ->andWhere('gs.targetSpice IS NOT NULL')
            ->andWhere('gs.finishedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
