<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message Messenger : déclenche le rebuild de la table spice_active_compound.
 *
 * Dispatché par SpiceConcentrationChangedListener lors de toute modification
 * de SpiceCompoundConcentration ou CompoundOdt.
 *
 * Traité en asynchrone par RecomputeOavTableHandler.
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §4.5
 */
final class RecomputeOavTableMessage
{
    /**
     * @param string $reason raison du rebuild (pour les logs — max 255 chars)
     */
    public function __construct(
        public readonly string $reason = 'manual',
    ) {
        if (trim($this->reason) === '') {
            throw new \InvalidArgumentException('RecomputeOavTableMessage::$reason must not be empty.');
        }
    }
}
