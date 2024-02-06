<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpicyType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpicyType>
 *
 * @method SpicyType|null find($id, $lockMode = null, $lockVersion = null)
 * @method SpicyType|null findOneBy(array $criteria, array $orderBy = null)
 * @method SpicyType[]    findAll()
 * @method SpicyType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpicyTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpicyType::class);
    }

    public function add(SpicyType $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->persist($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function remove(SpicyType $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }
}
