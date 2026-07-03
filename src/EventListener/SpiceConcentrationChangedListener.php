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

/**
 * Déclenche le rebuild de spice_active_compound après toute modification
 * de SpiceCompoundConcentration (concentration) ou CompoundOdt (seuil olfactif).
 *
 * ⚠️  Stratégie postFlush (dedup) :
 *     Les événements postPersist/postUpdate/postRemove marquent uniquement un flag.
 *     Un seul message est dispatché dans postFlush, quelle que soit la taille du flush.
 *     Sans cela, un import de 500 concentrations dispatche 500 rebuilds successifs.
 *
 * ⚠️  Couvre les deux entités source du calcul OAV = C/ODT :
 *     - SpiceCompoundConcentration (numérateur)
 *     - CompoundOdt (dénominateur)
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §4.4
 */
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

    /**
     * Dispatche UNE SEULE fois par flush (dedup).
     * Appelé après que toutes les entités du flush ont déclenché leurs lifecycle events.
     */
    public function postFlush(): void
    {
        if (! $this->pendingRecompute) {
            return;
        }

        // Reset AVANT le dispatch : si dispatch() déclenche un flush interne (transport Doctrine),
        // postFlush() serait rappelé avec $pendingRecompute=true → double dispatch.
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
        // Conserver la première raison connue (les suivantes sont des doublons du même flush)
        if ('' === $this->pendingReason) {
            $this->pendingReason = sprintf('%s.%s', $entityClass, $operation);
        }
    }
}
