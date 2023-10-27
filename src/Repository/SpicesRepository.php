<?php

namespace App\Repository;

use App\Entity\Spices;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Driver\Exception;
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
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Spices $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
    
    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getByMainAromaticsCompounds(array $mainAromaticsCompoundsIds, array $secondaryAromaticsCompoundsIds): array
    {
        $mainIds = $this->checkArrayAndReturnString($mainAromaticsCompoundsIds);
        $secondaryIds = $this->checkArrayAndReturnString($secondaryAromaticsCompoundsIds);

        $sql = "SELECT DISTINCT spices_id
                FROM spices_aromatic_compound 
                WHERE aromatic_compound_id IN ($mainIds)
                
                UNION
                    
                SELECT DISTINCT spices_id
                FROM secondary_spices_aromatic_compound 
                WHERE aromatic_compound_id IN ($mainIds)
                
                UNION
                
                SELECT DISTINCT spices_id
                FROM spices_aromatic_compound 
                WHERE aromatic_compound_id IN ($secondaryIds)
                    
                UNION
                
                SELECT DISTINCT spices_id
                FROM secondary_spices_aromatic_compound 
                WHERE aromatic_compound_id IN ($secondaryIds)";

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    private function checkArrayAndReturnString(array $array): string
    {
        if(count($array) === 0){
            return '0';
        }

        return implode(",", $array);
    }
}
