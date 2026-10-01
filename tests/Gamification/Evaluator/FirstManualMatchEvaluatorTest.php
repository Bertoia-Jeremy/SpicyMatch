<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Gamification\Evaluator\FirstManualMatchEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FirstManualMatchEvaluatorTest extends TestCase
{
    public function testTriggerAndEventType(): void
    {
        $evaluator = new FirstManualMatchEvaluator();

        self::assertSame(AchievementTrigger::FIRST_MANUAL_MATCH, $evaluator->trigger());
        self::assertSame(['match_saved'], $evaluator->eventTypes());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function contextProvider(): iterable
    {
        yield 'manual match' => [[
            'isManual' => true,
        ], true];
        yield 'suggested match' => [[
            'isManual' => false,
        ], false];
        yield 'missing flag' => [[], false];
        yield 'truthy non bool' => [[
            'isManual' => 1,
        ], false];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('contextProvider')]
    public function testIsMet(array $context, bool $expected): void
    {
        $achievement = new Achievement()
            ->setSlug('first_manual_match')
            ->setName('Main du Chef')
            ->setDescription('d')
            ->setTrigger(AchievementTrigger::FIRST_MANUAL_MATCH)
            ->setTriggerValue(1)
            ->setXpReward(15)
            ->setRarity(AchievementRarity::COMMON);

        self::assertSame($expected, new FirstManualMatchEvaluator()->isMet($achievement, new UserProgression(), $context));
    }
}
