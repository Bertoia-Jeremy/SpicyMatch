<?php

declare(strict_types=1);

namespace App\Enum;

use App\Service\Match\Strategy\AirMatrixStrategy;
use App\Service\Match\Strategy\MatrixStrategy;
use App\Service\Match\Strategy\OilMatrixStrategy;
use App\Service\Match\Strategy\WaterMatrixStrategy;

enum OdtMatrix: string
{
    case AIR = 'air';
    case WATER = 'water';
    case OIL = 'oil';

    /**
     * Clé de traduction.
     */
    public function label(): string
    {
        return 'enum.matrix.' . $this->value;
    }

    public function strategy(): MatrixStrategy
    {
        static $strategies = [];

        return $strategies[$this->value] ??= match ($this) {
            self::AIR => new AirMatrixStrategy(),
            self::WATER => new WaterMatrixStrategy(),
            self::OIL => new OilMatrixStrategy(),
        };
    }
}
