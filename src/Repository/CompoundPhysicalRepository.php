<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompoundPhysical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompoundPhysical>
 */
final class CompoundPhysicalRepository extends ServiceEntityRepository implements CompoundPhysicalRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompoundPhysical::class);
    }

    /**
     * Charge les propriétés physiques pour une liste de composés, indexées par compoundId.
     * Single query, pas de N+1.
     *
     * @param int[] $compoundIds
     *
     * @return array<int, CompoundPhysical>
     */
    public function loadByCompoundIds(array $compoundIds): array
    {
        if ($compoundIds === []) {
            return [];
        }

        // JOIN explicite : utilise l'index FK aromatic_compound_id directement.
        $rows = $this->createQueryBuilder('cp')
            ->join('cp.compound', 'c')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $compoundIds)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $physical) {
            $compoundId = $physical->getCompound()
                ->getId();
            if ($compoundId !== null) {
                $map[$compoundId] = $physical;
            }
        }

        return $map;
    }
}
