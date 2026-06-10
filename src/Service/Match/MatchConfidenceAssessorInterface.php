<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\ValueObject\Match\MortarIds;

interface MatchConfidenceAssessorInterface
{
    public function assess(MortarIds $mortar, OdtMatrix $matrix): DataConfidence;
}
