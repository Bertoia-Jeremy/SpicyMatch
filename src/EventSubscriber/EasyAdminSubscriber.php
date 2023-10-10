<?php

namespace App\EventSubscriber;

use App\Entity\AlchemyFlavors;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class EasyAdminSubscriber implements EventSubscriberInterface{

    private  $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger= $slugger;
    }

    public static function getSubscribedEvents()
    {
        return [
            BeforeEntityPersistedEvent::class => ['setDefaultInput'],
            BeforeEntityUpdatedEvent::class => ['setDefaultInput2'],
        ];
    }

    public function setDefaultInput(BeforeEntityPersistedEvent $event){
        $instance = $event->getEntityInstance();

        $instance->setCreatedAt(new \DateTime('now'))
            ->setUpdatedAt(new \DateTime('now'));
    }

    public function setDefaultInput2(BeforeEntityUpdatedEvent $event){
        $instance = $event->getEntityInstance();

        $instance->setUpdatedAt(new \DateTime('now'));
    }
}