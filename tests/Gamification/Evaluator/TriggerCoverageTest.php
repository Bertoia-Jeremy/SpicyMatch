<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Enum\AchievementTrigger;
use App\Gamification\Evaluator\TriggerEvaluatorRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TriggerCoverageTest extends KernelTestCase
{
    public function testEveryTriggerHasAnEvaluator(): void
    {
        $registry = self::getContainer()->get(TriggerEvaluatorRegistry::class);

        $missing = [];
        foreach (AchievementTrigger::cases() as $trigger) {
            if ($registry->for($trigger) === null) {
                $missing[] = $trigger->value;
            }
        }

        self::assertSame([], $missing, sprintf('Triggers without an evaluator: %s', implode(', ', $missing)));
    }

    public function testEveryTriggerIsReachableByAtLeastOneEvent(): void
    {
        $registry = self::getContainer()->get(TriggerEvaluatorRegistry::class);

        $reachable = [];
        foreach (['match_saved', 'spice_read', 'favorite_toggled', 'easter_egg_found', 'game_completed', 'content_read'] as $event) {
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
