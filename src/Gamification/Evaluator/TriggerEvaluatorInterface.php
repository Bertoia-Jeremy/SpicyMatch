<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One evaluator per AchievementTrigger — replaces the god-switch in AchievementChecker.
 *
 * Implementations are auto-tagged via AutoconfigureTag and consumed by TriggerEvaluatorRegistry.
 *
 * Evaluators that can expose a numeric progress value to drive a progress bar
 * should additionally implement {@see ProgressTrackableEvaluator}.
 */
#[AutoconfigureTag('gamification.trigger_evaluator')]
interface TriggerEvaluatorInterface
{
    public function trigger(): AchievementTrigger;

    /**
     * Event types this evaluator listens to — canonical source for the
     * eventType → triggers[] mapping, indexed by the Registry at boot.
     *
     * @return list<string>
     */
    public function eventTypes(): array;

    /**
     * Whether the given achievement is unlocked right now, given the progression + event context.
     *
     * @param array<string, mixed> $context
     */
    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool;
}
