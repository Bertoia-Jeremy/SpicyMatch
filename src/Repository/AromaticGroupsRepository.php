<?php

namespace App\Repository;

use App\Entity\AromaticGroups;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AromaticGroups>
 *
 * @method AromaticGroups|null find($id, $lockMode = null, $lockVersion = null)
 * @method AromaticGroups|null findOneBy(array $criteria, array $orderBy = null)
 * @method AromaticGroups[]    findAll()
 * @method AromaticGroups[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AromaticGroupsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AromaticGroups::class);
    }

    public function add(AromaticGroups $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AromaticGroups $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
