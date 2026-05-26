<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompoundPhysical;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompoundPhysical>
 */
class CompoundPhysicalRepository extends ServiceEntityRepository
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

        $rows = $this->createQueryBuilder('cp')
            ->where('IDENTITY(cp.compound) IN (:ids)')
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
