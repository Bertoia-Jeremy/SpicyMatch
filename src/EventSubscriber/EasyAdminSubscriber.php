<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EasyAdminSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => ['setDefaultInput'],
            BeforeEntityUpdatedEvent::class => ['setDefaultInput2'],
        ];
    }

    /**
     * @param BeforeEntityPersistedEvent<object> $event
     */
    public function setDefaultInput(BeforeEntityPersistedEvent $event): void
    {
        $instance = $event->getEntityInstance();

        $instance->setCreatedAt(new \DateTimeImmutable('now'))
            ->setUpdatedAt(new \DateTimeImmutable('now'));
    }

    /**
     * @param BeforeEntityUpdatedEvent<object> $event
     */
    public function setDefaultInput2(BeforeEntityUpdatedEvent $event): void
    {
        $instance = $event->getEntityInstance();

        $instance->setUpdatedAt(new \DateTimeImmutable('now'));
    }
}
