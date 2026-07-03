<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpicyType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpicyType>
 */
class SpicyTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpicyType::class);
    }

    public function add(SpicyType $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->persist($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function remove(SpicyType $entity, bool $flush = false): void
    {
        $this->getEntityManager()
            ->remove($entity);

        if ($flush) {
            $this->getEntityManager()
                ->flush();
        }
    }

    public function findOneByLocalizedSlug(string $slug, string $locale): ?SpicyType
    {
        if ('fr' !== $locale) {
            $translated = $this->createQueryBuilder('e')
                ->innerJoin('e.translations', 't', 'WITH', 't.locale = :loc AND t.slug = :slug')
                ->setParameter('loc', $locale)
                ->setParameter('slug', $slug)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (null !== $translated) {
                return $translated;
            }
        }

        return $this->findOneBy([
            'slug' => $slug,
        ]);
    }
}
