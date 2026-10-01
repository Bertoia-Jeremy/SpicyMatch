<?php

declare(strict_types=1);

namespace App\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementTrigger;
use App\Enum\ContentKind;

final class AllContentKindsReadEvaluator implements TriggerEvaluatorInterface, ProgressTrackableEvaluator
{
    public function trigger(): AchievementTrigger
    {
        return AchievementTrigger::ALL_CONTENT_KINDS_READ;
    }

    public function eventTypes(): array
    {
        return ['spice_read', 'content_read'];
    }

    public function currentValue(UserProgression $progression, array $context): int
    {
        return $progression->getUser()?->getStats()?->countReadContentKinds() ?? 0;
    }

    public function isMet(Achievement $achievement, UserProgression $progression, array $context): bool
    {
        return ContextFilter::matches($achievement, $context)
            && $this->currentValue($progression, $context) >= \count(ContentKind::cases());
    }
}
