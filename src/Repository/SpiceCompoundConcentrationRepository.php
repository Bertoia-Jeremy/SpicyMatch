<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpiceCompoundConcentration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpiceCompoundConcentration>
 */
class SpiceCompoundConcentrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpiceCompoundConcentration::class);
    }

    /**
     * Retourne le nombre de concentrations enregistrées.
     */
    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.spice)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param int[] $spiceIds
     *
     * @return array<int, array<int, float>> spice_id => [compound_id => concentration_ppm]
     */
    public function findConcentrationsForSpices(array $spiceIds): array
    {
        if ($spiceIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative(
                sprintf(
                    'SELECT spice_id, aromatic_compound_id, concentration_ppm
                     FROM spice_compound_concentration
                     WHERE spice_id IN (%s)',
                    implode(',', array_fill(0, count($spiceIds), '?'))
                ),
                $spiceIds
            );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['spice_id']][(int) $row['aromatic_compound_id']] = (float) $row['concentration_ppm'];
        }

        return $result;
    }
}
