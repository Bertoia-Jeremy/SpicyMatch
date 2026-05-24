<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpiceActiveCompound;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpiceActiveCompound>
 *
 * Les lectures se font via DBAL pour éviter l'overhead de l'hydratation Doctrine
 * (la table est lue en masse, pas épice par épice).
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
    public function loadOavProfilesBatch(array $spiceIds): array
    {
        if ($spiceIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()
            ->getConnection()
            ->fetchAllAssociative(
                'SELECT spice_id, aromatic_compound_id, oav_value
                 FROM spice_active_compound
                 WHERE spice_id IN (:ids)',
                [
                    'ids' => $spiceIds,
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
     * Vérifie si au moins une épice du mortier a des données OAV disponibles.
     * Utilisé par MatchPipeline pour choisir entre mode OAV et mode fallback présence.
     *
     * @param int[] $spiceIds
     */
    public function hasOavDataForSpices(array $spiceIds): bool
    {
        if ($spiceIds === []) {
            return false;
        }

        // SELECT 1 LIMIT 1 = early exit réel (COUNT(*) retourne toujours 1 ligne, LIMIT 1 y est un no-op)
        return $this->getEntityManager()
            ->getConnection()
            ->fetchOne(
                'SELECT 1 FROM spice_active_compound WHERE spice_id IN (:ids) LIMIT 1',
                [
                    'ids' => $spiceIds,
                ],
                [
                    'ids' => ArrayParameterType::INTEGER,
                ],
            ) !== false;
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
}
