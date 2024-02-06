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

    public function setDefaultInput(BeforeEntityPersistedEvent $event): void
    {
        $instance = $event->getEntityInstance();

        $instance->setCreatedAt(new \DateTime('now'))
            ->setUpdatedAt(new \DateTime('now'));
    }

    public function setDefaultInput2(BeforeEntityUpdatedEvent $event): void
    {
        $instance = $event->getEntityInstance();

        $instance->setUpdatedAt(new \DateTime('now'));
    }
}
