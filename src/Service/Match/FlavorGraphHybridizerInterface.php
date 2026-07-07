<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\ValueObject\Match\MortarIds;

interface FlavorGraphHybridizerInterface
{
    /**
     * @param list<array{id: int, score: int, oav_mode: bool}> $results
     *
     * @return list<array{id: int, score: int, oav_mode: bool}>
     */
    public function rerank(
        array $results,
        MortarIds $mortar,
        bool $oavMode,
        OdtMatrix $matrix,
        ?DataConfidence $tier = null,
    ): array;

    public function isActive(): bool;
}
