<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Users>
 */
class UsersRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Users::class);
    }

    public function addOrUpdate(Users $user): void
    {
        $this->getEntityManager()
            ->persist($user);
        $this->getEntityManager()
            ->flush();
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (! $user instanceof Users) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);

        $this->addOrUpdate($user);
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return list<Users>
     */
    public function findNonDeletedBy(array $criteria): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.deleted_at IS NULL')
            ->andWhere(implode(' AND ', array_map(
                static fn (string $key): string => 'u.' . $key . ' = :' . $key,
                array_keys($criteria)
            )));

        foreach ($criteria as $key => $value) {
            $qb->setParameter($key, $value);
        }

        return $qb->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.deleted_at IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
