<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Translation\Sluggable;
use App\Entity\Translation\TranslationInterface;
use App\Service\Slug\SlugGenerator;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class SlugListener
{
    public function __construct(
        private readonly SlugGenerator $generator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->assignSlug($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (! $this->assignSlug($entity)) {
            return;
        }

        $meta = $this->em->getClassMetadata($entity::class);
        $this->em->getUnitOfWork()
            ->recomputeSingleEntityChangeSet($meta, $entity);
    }

    private function assignSlug(object $entity): bool
    {
        if (! $entity instanceof Sluggable) {
            return false;
        }

        $current = $entity->getSlug();
        if ($current !== null && $current !== '') {
            return false;
        }

        $name = $entity->getName();
        if ($name === null || $name === '') {
            return false;
        }

        $locale = $entity instanceof TranslationInterface ? $entity->getLocale() : null;
        $class = $entity::class;

        $entity->setSlug($this->generator->unique(
            $name,
            fn (string $candidate): bool => $this->slugTaken($class, $candidate, $locale),
        ));

        return true;
    }

    /**
     * @param class-string $class
     */
    private function slugTaken(string $class, string $slug, ?string $locale): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($class, 'e')
            ->where('e.slug = :slug')
            ->setParameter('slug', $slug);

        if ($locale !== null) {
            $qb->andWhere('e.locale = :loc')
                ->setParameter('loc', $locale);
        }

        return (int) $qb->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
