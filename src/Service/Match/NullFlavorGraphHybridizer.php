<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\ValueObject\Match\MortarIds;

final class NullFlavorGraphHybridizer implements FlavorGraphHybridizerInterface
{
    public function rerank(
        array $results,
        MortarIds $mortar,
        bool $oavMode,
        OdtMatrix $matrix,
        ?DataConfidence $tier = null,
    ): array {
        return $results;
    }

    public function isActive(): bool
    {
        return false;
    }
}
