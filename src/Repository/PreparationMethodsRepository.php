<?php

namespace App\Repository;

use App\Entity\PreparationMethods;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreparationMethods>
 */
class PreparationMethodsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreparationMethods::class);
    }

    public function findOneByLocalizedSlug(string $slug, string $locale): ?PreparationMethods
    {
        if ($locale !== 'fr') {
            $translated = $this->createQueryBuilder('e')
                ->innerJoin('e.translations', 't', 'WITH', 't.locale = :loc AND t.slug = :slug')
                ->setParameter('loc', $locale)
                ->setParameter('slug', $slug)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($translated !== null) {
                return $translated;
            }
        }

        return $this->findOneBy([
            'slug' => $slug,
        ]);
    }
}
