<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\UserProgression;

interface ProgressTrackableEvaluator
{
    /**
     * @param array<string, mixed> $context
     */
    public function currentValue(UserProgression $progression, array $context): int;
}
