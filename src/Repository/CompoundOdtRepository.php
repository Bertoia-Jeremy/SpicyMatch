<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompoundOdt;
use App\Enum\OdtMatrix;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompoundOdt>
 */
class CompoundOdtRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompoundOdt::class);
    }

    /**
     * Compte le nombre total d'entrées ODT (toutes matrices confondues).
     */
    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.aromaticCompound)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve l'ODT pour un composé dans la matrice donnée (air par défaut).
     */
    public function findForCompound(int $aromaticCompoundId, OdtMatrix $matrix = OdtMatrix::AIR): ?CompoundOdt
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT o FROM App\Entity\CompoundOdt o
                 WHERE o.aromaticCompound = :id AND o.matrix = :matrix'
            )
            ->setParameter('id', $aromaticCompoundId)
            ->setParameter('matrix', $matrix->value)
            ->getOneOrNullResult();
    }

    /**
     * @return array<int, float> aromatic_compound_id => odt_ppm pour la matrice donnée
     */
    public function findAllForMatrix(OdtMatrix $matrix = OdtMatrix::AIR): array
    {
        $rows = $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative(
                'SELECT aromatic_compound_id, odt_ppm FROM compound_odt WHERE matrix = ?',
                [$matrix->value]
            );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['aromatic_compound_id']] = (float) $row['odt_ppm'];
        }

        return $result;
    }
}
