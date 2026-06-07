<?php

declare(strict_types=1);

namespace App\Enum;

enum OdtMatrix: string
{
    case AIR = 'air';
    case WATER = 'water';
    case OIL = 'oil';

    /**
     * Clé de traduction (domaine messages) — traduire à l'affichage via |trans.
     */
    public function label(): string
    {
        return 'enum.matrix.' . $this->value;
    }
}
