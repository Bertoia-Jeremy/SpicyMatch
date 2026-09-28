<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\CompoundOdt;
use App\Entity\SpiceCompoundConcentration;
use App\Message\RecomputeOavTableMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class SpiceConcentrationChangedListener
{
    private bool $pendingRecompute = false;

    private string $pendingReason = '';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param LifecycleEventArgs<EntityManagerInterface> $args
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->markIfRelevant($args->getObject(), 'persist');
    }

    /**
     * @param LifecycleEventArgs<EntityManagerInterface> $args
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->markIfRelevant($args->getObject(), 'update');
    }

    /**
     * @param LifecycleEventArgs<EntityManagerInterface> $args
     */
    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->markIfRelevant($args->getObject(), 'remove');
    }

    public function postFlush(): void
    {
        if (! $this->pendingRecompute) {
            return;
        }

        $this->pendingRecompute = false;
        $reason = $this->pendingReason;
        $this->pendingReason = '';

        $this->messageBus->dispatch(new RecomputeOavTableMessage($reason));
    }

    private function markIfRelevant(object $entity, string $operation): void
    {
        if (! $entity instanceof SpiceCompoundConcentration && ! $entity instanceof CompoundOdt) {
            return;
        }

        $entityClass = $entity instanceof CompoundOdt ? 'CompoundOdt' : 'SpiceCompoundConcentration';

        $this->pendingRecompute = true;
        if ($this->pendingReason === '') {
            $this->pendingReason = sprintf('%s.%s', $entityClass, $operation);
        }
    }
}
