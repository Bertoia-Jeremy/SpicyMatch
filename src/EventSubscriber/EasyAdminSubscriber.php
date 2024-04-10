<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\CookingTips;
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

        if (! ($instance instanceof CookingTips)) {
            return;
        }

        $this->setCookingTipsStep($instance);
    }

    public function setDefaultInput2(BeforeEntityUpdatedEvent $event): void
    {
        $instance = $event->getEntityInstance();

        $instance->setUpdatedAt(new \DateTime('now'));
    }

    private function setCookingTipsStep(CookingTips $cookingTips): void
    {
        $arraySteps = [
            'Avant' => 0,
            'Début' => 1,
            'Milieu' => 2,
            'Fin' => 3,
            'Après' => 4,
        ];

        $cookingTips->setStep($arraySteps[$cookingTips->getCookingStep()]);
    }
}
