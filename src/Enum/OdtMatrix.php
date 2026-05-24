<?php

declare(strict_types=1);

namespace App\Enum;

enum OdtMatrix: string
{
    case AIR = 'air';
    case WATER = 'water';
    case OIL = 'oil';

    public function label(): string
    {
        return match ($this) {
            OdtMatrix::AIR => 'Air',
            OdtMatrix::WATER => 'Eau',
            OdtMatrix::OIL => 'Huile',
        };
    }
}
