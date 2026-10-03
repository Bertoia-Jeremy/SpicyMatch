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

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.aromaticCompound)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findForCompound(int $aromaticCompoundId, OdtMatrix $matrix): ?CompoundOdt
    {
        return $this->getEntityManager()
            ->createQuery(
                'SELECT o FROM App\Entity\CompoundOdt o
                 WHERE o.aromaticCompound = :id AND o.matrix = :matrix',
            )
            ->setParameter('id', $aromaticCompoundId)
            ->setParameter('matrix', $matrix->value)
            ->getOneOrNullResult();
    }

    /**
     * @return list<CompoundOdt>
     */
    public function findAllForCompound(int $aromaticCompoundId): array
    {
        $rows = $this->createQueryBuilder('o')
            ->andWhere('o.aromaticCompound = :id')
            ->setParameter('id', $aromaticCompoundId)
            ->getQuery()
            ->getResult();

        $rank = array_flip(array_map(static fn (OdtMatrix $m): string => $m->value, OdtMatrix::cases()));
        usort($rows, static fn (CompoundOdt $a, CompoundOdt $b): int => $rank[$a->getMatrix()->value] <=> $rank[$b->getMatrix()->value]);

        return $rows;
    }
}
