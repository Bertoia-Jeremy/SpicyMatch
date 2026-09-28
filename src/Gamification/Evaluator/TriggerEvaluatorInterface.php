<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('gamification.trigger_evaluator')]
interface TriggerEvaluatorInterface
{
    public function trigger(): AchievementTrigger;

    /**
     * @return list<string>
     */
    public function eventTypes(): array;

    /**
     * @param array<string, mixed> $context
     */
    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool;
}
