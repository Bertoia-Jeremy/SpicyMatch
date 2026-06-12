<?php

declare(strict_types=1);

namespace App\Service\Match;

use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;

interface MatchPipelineInterface
{
    /**
     * @param int $limit ≥ 1, ≤ 100
     *
     * @return list<array{id: int, score: int, oav_mode: bool}>
     */
    public function run(MortarIds $mortar, int $limit, CulinaryContext $ctx): array;
}
