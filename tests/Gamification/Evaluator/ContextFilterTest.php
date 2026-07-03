<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Gamification\Evaluator\ContextFilter;
use PHPUnit\Framework\TestCase;

/**
 * The ContextFilter gates achievement evaluation by gameMode/difficulty filters.
 * Every evaluator depends on it — a regression here silently unlocks achievements
 * that should have been scoped to specific modes/difficulties.
 */
final class ContextFilterTest extends TestCase
{
    public function testPassesWhenAchievementHasNoFilters(): void
    {
        $achievement = $this->achievement(null, null);

        self::assertTrue(ContextFilter::matches($achievement, []));
        self::assertTrue(ContextFilter::matches($achievement, [
            'gameMode' => 'whatever',
        ]));
    }

    public function testRejectsWhenExpectedModeAbsentFromContext(): void
    {
        $achievement = $this->achievement(GameMode::INTRUS, null);

        self::assertFalse(ContextFilter::matches($achievement, []), 'missing mode should not match');
    }

    public function testMatchesModeAsEnum(): void
    {
        $achievement = $this->achievement(GameMode::INTRUS, null);

        self::assertTrue(ContextFilter::matches($achievement, [
            'gameMode' => GameMode::INTRUS,
        ]));
    }

    public function testMatchesModeAsString(): void
    {
        $achievement = $this->achievement(GameMode::INTRUS, null);

        self::assertTrue(ContextFilter::matches($achievement, [
            'gameMode' => 'intrus',
        ]));
    }

    public function testRejectsWrongMode(): void
    {
        $achievement = $this->achievement(GameMode::INTRUS, null);

        self::assertFalse(ContextFilter::matches($achievement, [
            'gameMode' => GameMode::CHRONO,
        ]));
        self::assertFalse(ContextFilter::matches($achievement, [
            'gameMode' => 'chrono',
        ]));
    }

    public function testRejectsWhenModeContextTypeInvalid(): void
    {
        $achievement = $this->achievement(GameMode::INTRUS, null);

        self::assertFalse(ContextFilter::matches($achievement, [
            'gameMode' => 42,
        ]));
    }

    public function testPassesDifficultyMatch(): void
    {
        $achievement = $this->achievement(null, GameDifficulty::HARD);

        self::assertTrue(ContextFilter::matches($achievement, [
            'difficulty' => GameDifficulty::HARD,
        ]));
        self::assertTrue(ContextFilter::matches($achievement, [
            'difficulty' => 'hard',
        ]));
    }

    public function testRejectsDifficultyMismatch(): void
    {
        $achievement = $this->achievement(null, GameDifficulty::HARD);

        self::assertFalse(ContextFilter::matches($achievement, [
            'difficulty' => GameDifficulty::EASY,
        ]));
    }

    public function testBothModeAndDifficultyMustMatch(): void
    {
        $achievement = $this->achievement(GameMode::CHRONO, GameDifficulty::HARD);

        // Mode OK, difficulty wrong → reject
        self::assertFalse(ContextFilter::matches($achievement, [
            'gameMode' => GameMode::CHRONO,
            'difficulty' => GameDifficulty::EASY,
        ]));

        // Difficulty OK, mode wrong → reject
        self::assertFalse(ContextFilter::matches($achievement, [
            'gameMode' => GameMode::INTRUS,
            'difficulty' => GameDifficulty::HARD,
        ]));

        // Both match → pass
        self::assertTrue(ContextFilter::matches($achievement, [
            'gameMode' => GameMode::CHRONO,
            'difficulty' => GameDifficulty::HARD,
        ]));
    }

    private function achievement(?GameMode $mode, ?GameDifficulty $difficulty): Achievement
    {
        $achievement = new Achievement();
        if (null !== $mode) {
            $achievement->setContextGameMode($mode);
        }
        if (null !== $difficulty) {
            $achievement->setContextDifficulty($difficulty);
        }

        return $achievement;
    }
}
