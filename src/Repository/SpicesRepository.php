<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Spices;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Spices>
 *
 * @method Spices|null find($id, $lockMode = null, $lockVersion = null)
 * @method Spices|null findOneBy(array $criteria, array $orderBy = null)
 * @method Spices[]    findAll()
 * @method Spices[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SpicesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Spices::class);
    }

    public function add(Spices $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->persist($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function remove(Spices $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function findAllByStringIds(string $stringIds): array
    {
        $arrayIds = explode(',', $stringIds);

        return $this->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->setParameter('ids', $arrayIds)
            ->orderBy('s.aromaticGroups')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<array<string>>
     */
    public function findSpicesForMatch(string $idsString): array
    {
        return $this->findAllSpices($idsString);
    }

    /**
     * @return array<array<string>>
     */
    public function findAllSpices(?string $ids = null): array
    {
        $glue = '';

        if ($ids) {
            $glue .= "WHERE s.id IN ({$ids})";
        }

        $sql = "SELECT s.id, s.name, s.description, s.file, ac.color, ac.name as groupName
                FROM spices AS s
                LEFT JOIN aromatic_groups AS ac
                    ON s.aromaticGroups = ac.id
                {$glue}
                ORDER BY groupName, s.name";

        $stmt = $this->getEntityManager()
            ->getConnection()
            ->prepare($sql);

        return $stmt->executeQuery()
            ->fetchAllAssociative();
    }

    public function search(string $word): array
    {
        $sql = 'SELECT s.id, s.name, IF(1, "spice", "") as `type`
                FROM spices s
                WHERE s.name LIKE ?
                    AND deleted_at IS NULL
                UNION
                SELECT ac.id, ac.name, IF(1, "aromatic_coumpound", "") as `type`
                FROM aromatic_compound ac
                WHERE ac.name LIKE ?
                    AND deleted_at IS NULL
                ORDER BY `name`
                LIMIT 10';

        $stmt = $this->getEntityManager()
            ->getConnection()
            ->prepare($sql);
        $stmt->bindValue(1, '%' . $word . '%');
        $stmt->bindValue(2, '%' . $word . '%');

        return $stmt->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return array<string>
     */
    public function getByAromaticsCompounds(
        array $mainAromaticsCompoundsIds,
        array $secondaryAromaticsCompoundsIds,
    ): array {
        $mainIds = $this->checkArrayAndReturnString($mainAromaticsCompoundsIds);
        $secondaryIds = $this->checkArrayAndReturnString($secondaryAromaticsCompoundsIds);

        $sql = "SELECT DISTINCT spices_id
                FROM spices_aromatic_compound 
                WHERE aromatic_compound_id IN ({$mainIds})
                
                UNION
                    
                SELECT DISTINCT spices_id
                FROM secondary_spices_aromatic_compound 
                WHERE aromatic_compound_id IN ({$mainIds})
                
                UNION
                
                SELECT DISTINCT spices_id
                FROM spices_aromatic_compound 
                WHERE aromatic_compound_id IN ({$secondaryIds})
                    
                UNION
                
                SELECT DISTINCT spices_id
                FROM secondary_spices_aromatic_compound 
                WHERE aromatic_compound_id IN ({$secondaryIds})";

        $conn = $this->getEntityManager()
            ->getConnection();
        $stmt = $conn->prepare($sql);

        $arraysIds = $stmt->executeQuery()
            ->fetchAllAssociative();

        // Afin de ne retourner qu'un seul tableau d'ids
        $ids = [];
        foreach ($arraysIds as $array) {
            $ids[] = $array['spices_id'];
        }

        return $ids;
    }

    private function checkArrayAndReturnString(array $array): string
    {
        if ($array === []) {
            return '0';
        }

        return implode(',', $array);
    }
}
