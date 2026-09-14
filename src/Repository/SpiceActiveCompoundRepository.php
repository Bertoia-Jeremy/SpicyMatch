<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpiceActiveCompound;
use App\Enum\OdtMatrix;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpiceActiveCompound>
 */
class SpiceActiveCompoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpiceActiveCompound::class);
    }

    /**
     * @param int[] $spiceIds
     * @return array<int, array<int, float>> spice_id => [compound_id => oav_value]
     */
    public function loadOavProfilesBatch(array $spiceIds, OdtMatrix $matrix): array
    {
        if ($spiceIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative(
                'SELECT spice_id, aromatic_compound_id, oav_value
                 FROM spice_active_compound
                 WHERE spice_id IN (:ids)
                   AND matrix = :matrix',
                [
                    'ids' => $spiceIds,
                    'matrix' => $matrix->value,
                ],
                [
                    'ids' => ArrayParameterType::INTEGER,
                ],
            );

        $profiles = [];
        foreach ($rows as $row) {
            $profiles[(int) $row['spice_id']][(int) $row['aromatic_compound_id']] = (float) $row['oav_value'];
        }

        return $profiles;
    }

    public function countTotal(): int
    {
        return (int) $this->getEntityManager()
            ->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM spice_active_compound');
    }

    /**
     * @return list<string> valeurs OdtMatrix présentes (sous-ensemble de air|water|oil)
     */
    public function matricesWithData(): array
    {
        $rows = $this->getEntityManager()
            ->getConnection()
            ->fetchFirstColumn('SELECT DISTINCT matrix FROM spice_active_compound');

        return array_map(static fn (mixed $m): string => (string) $m, $rows);
    }

    /**
     * @param int[] $spiceIds
     */
    public function hasDataForSpices(array $spiceIds, OdtMatrix $matrix): bool
    {
        if ($spiceIds === []) {
            return false;
        }

        return $this->loadOavProfilesBatch($spiceIds, $matrix) !== [];
    }
}
