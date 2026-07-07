<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\IngredientPairing;
use App\ValueObject\Match\MortarIds;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IngredientPairing>
 */
class FlavorGraphAffinityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IngredientPairing::class);
    }

    /**
     * @param list<int> $candidateIds
     *
     * @return array<int, array<int, float>> candidate_id => [mortar_id => affinity_score]
     */
    public function loadPairwiseBatch(array $candidateIds, MortarIds $mortar): array
    {
        if ([] === $candidateIds) {
            return [];
        }

        $rows = $this->getEntityManager()
            ->getConnection()
            ->executeQuery(
                'SELECT spice_a_id, spice_b_id, affinity_score
             FROM ingredient_pairing
             WHERE spice_a_id IN (:candidates) AND spice_b_id IN (:mortar)',
                [
                    'candidates' => $candidateIds,
                    'mortar' => $mortar->toArray(),
                ],
                [
                    'candidates' => ArrayParameterType::INTEGER,
                    'mortar' => ArrayParameterType::INTEGER,
                ],
            )->fetchAllAssociative();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['spice_a_id']][(int) $row['spice_b_id']] = (float) $row['affinity_score'];
        }

        return $map;
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(1)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
