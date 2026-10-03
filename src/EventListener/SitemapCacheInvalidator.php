<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AlchemyFlavorsTranslation;
use App\Entity\AromaticCompoundTranslation;
use App\Entity\AromaticGroupsTranslation;
use App\Entity\PreparationMethodsTranslation;
use App\Entity\SpiceTranslation;
use App\Entity\SpicyTypeTranslation;
use App\EventSubscriber\SitemapSubscriber;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final readonly class SitemapCacheInvalidator
{
    /**
     * @var list<class-string>
     */
    private const array TRANSLATION_CLASSES = [
        SpiceTranslation::class,
        AromaticCompoundTranslation::class,
        AlchemyFlavorsTranslation::class,
        SpicyTypeTranslation::class,
        PreparationMethodsTranslation::class,
        AromaticGroupsTranslation::class,
    ];

    public function __construct(
        #[Target('sitemap.cache')]
        private CacheInterface $cache,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateIfListed($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateIfListed($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateIfListed($args->getObject());
    }

    private function invalidateIfListed(object $entity): void
    {
        foreach ([...array_values(SitemapSubscriber::DETAIL_ROUTES), ...self::TRANSLATION_CLASSES] as $class) {
            if ($entity instanceof $class) {
                $this->cache->delete(SitemapSubscriber::CACHE_KEY);

                return;
            }
        }
    }
}
