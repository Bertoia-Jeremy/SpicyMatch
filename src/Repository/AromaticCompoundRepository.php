<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AromaticCompound;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AromaticCompound>
 *
 * @method AromaticCompound|null find($id, $lockMode = null, $lockVersion = null)
 * @method AromaticCompound|null findOneBy(array $criteria, array $orderBy = null)
 * @method AromaticCompound[]    findAll()
 * @method AromaticCompound[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AromaticCompoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AromaticCompound::class);
    }

    public function add(AromaticCompound $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->persist($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function remove(AromaticCompound $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function search(string $word){
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT ac.id, ac.name
            FROM App\Entity\AromaticCompound ac
            WHERE ac.name LIKE :word
            AND ac.deleted_at IS NULL'
        )->setParameter('word', '%'.$word.'%');

        return $query->getArrayResult();
    }
}
