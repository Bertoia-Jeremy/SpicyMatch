<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Enum\AchievementTrigger;
use App\Gamification\Evaluator\TriggerEvaluatorRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Architectural guard: every AchievementTrigger enum case MUST have exactly one
 * evaluator registered. Adding a new enum case without a tagged evaluator would
 * silently break progress tracking — this test fails fast.
 */
final class TriggerCoverageTest extends KernelTestCase
{
    public function testEveryTriggerHasAnEvaluator(): void
    {
        $registry = self::getContainer()->get(TriggerEvaluatorRegistry::class);

        $missing = [];
        foreach (AchievementTrigger::cases() as $trigger) {
            if (null === $registry->for($trigger)) {
                $missing[] = $trigger->value;
            }
        }

        self::assertSame([], $missing, sprintf('Triggers without an evaluator: %s', implode(', ', $missing)));
    }

    public function testEveryTriggerIsReachableByAtLeastOneEvent(): void
    {
        $registry = self::getContainer()->get(TriggerEvaluatorRegistry::class);

        $reachable = [];
        // Enumerate all declared eventTypes() across the registered evaluators.
        foreach (['match_saved', 'spice_read', 'favorite_toggled', 'easter_egg_found', 'game_completed'] as $event) {
            foreach ($registry->forEvent($event) as $evaluator) {
                $reachable[$evaluator->trigger()->value] = true;
            }
        }

        $orphans = [];
        foreach (AchievementTrigger::cases() as $trigger) {
            if (! isset($reachable[$trigger->value])) {
                $orphans[] = $trigger->value;
            }
        }

        self::assertSame([], $orphans, sprintf('Triggers not wired to any event: %s', implode(', ', $orphans)));
    }
}
