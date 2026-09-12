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
 *
 * Les lectures se font via DBAL pour éviter l'overhead de l'hydratation Doctrine
 * (la table est lue en masse, pas épice par épice).
 *
 * Toutes les méthodes acceptent un paramètre `$matrix` (défaut: AIR) pour
 * filtrer sur la bonne matrice ODT.
 */
class SpiceActiveCompoundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpiceActiveCompound::class);
    }

    /**
     * Charge les profils OAV de plusieurs épices en une seule requête SQL.
     *
     * @param int[] $spiceIds
     *
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

    /**
     * Retourne le nombre total d'entrées OAV-actives (diagnostic).
     */
    public function countTotal(): int
    {
        return (int) $this->getEntityManager()
            ->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM spice_active_compound');
    }

    /**
     * Matrices ayant au moins une entrée OAV-active (véracité par omission :
     * une matrice sans données ne doit pas être proposée dans l'UI).
     *
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
     * Vrai si au moins une des épices données possède un composé OAV-actif dans la matrice.
     * Sert à distinguer scoring OAV réel vs repli présence (pas de score quantitatif).
     *
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
