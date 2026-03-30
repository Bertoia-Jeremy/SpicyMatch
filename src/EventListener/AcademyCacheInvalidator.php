<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Spices;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\CacheInterface;

#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postRemove)]
class AcademyCacheInvalidator
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateIfSpice($args->getObject());
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateIfSpice($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateIfSpice($args->getObject());
    }

    private function invalidateIfSpice(object $entity): void
    {
        if (! $entity instanceof Spices) {
            return;
        }

        $this->cache->delete('academy.spice_cards');

        if ($entity->getId() !== null) {
            $this->cache->delete('academy.intruders.' . $entity->getId());
        }
    }
}
