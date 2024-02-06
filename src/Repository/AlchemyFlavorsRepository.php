<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AlchemyFlavors;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlchemyFlavors>
 *
 * @method AlchemyFlavors|null find($id, $lockMode = null, $lockVersion = null)
 * @method AlchemyFlavors|null findOneBy(array $criteria, array $orderBy = null)
 * @method AlchemyFlavors[]    findAll()
 * @method AlchemyFlavors[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlchemyFlavorsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlchemyFlavors::class);
    }

    public function add(AlchemyFlavors $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->persist($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function remove(AlchemyFlavors $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }
}
