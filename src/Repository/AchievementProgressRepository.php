<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\AchievementProgress;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AchievementProgress>
 *
 * @method AchievementProgress|null find($id, $lockMode = null, $lockVersion = null)
 * @method AchievementProgress|null findOneBy(array $criteria, array $orderBy = null)
 * @method AchievementProgress[]    findAll()
 * @method AchievementProgress[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AchievementProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AchievementProgress::class);
    }

    public function findOrCreateForUser(Users $user, Achievement $achievement): AchievementProgress
    {
        $ap = $this->findOneBy([
            'user' => $user,
            'achievement' => $achievement,
        ]);
        if ($ap === null) {
            $ap = new AchievementProgress();
            $ap->setUser($user)
                ->setAchievement($achievement);
            $this->getEntityManager()
                ->persist($ap);
        }

        return $ap;
    }

    /**
     * @return AchievementProgress[]
     */
    public function findByUser(Users $user): array
    {
        return $this->findBy([
            'user' => $user,
        ]);
    }

    /**
     * Retourne l'achievement en cours le plus avancé (non complété) pour affichage dans le banner home.
     * Trie par (progress / triggerValue) DESC pour prioriser le plus proche de la complétion.
     */
    public function findMostAdvancedNotCompleted(Users $user): ?AchievementProgress
    {
        return $this->createQueryBuilder('ap')
            ->join('ap.achievement', 'a')
            ->addSelect('a')
            ->where('ap.user = :user')
            ->andWhere('ap.progress < a.triggerValue')
            ->andWhere('ap.progress > 0')
            ->orderBy('ap.progress / a.triggerValue', 'DESC')
            ->setMaxResults(1)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
