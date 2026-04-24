<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\UserProgression;

/**
 * Optional capability for {@see TriggerEvaluatorInterface} impls that can
 * report a numeric progress value to drive user-facing progress bars.
 *
 * Evaluators for one-shot triggers (easter eggs, perfect runs) don't implement
 * this interface — the ISP split keeps `currentValue()` honest.
 */
interface ProgressTrackableEvaluator
{
    /**
     * Current progress value for this trigger.
     *
     * @param array<string, mixed> $context
     */
    public function currentValue(UserProgression $progression, array $context): int;
}
