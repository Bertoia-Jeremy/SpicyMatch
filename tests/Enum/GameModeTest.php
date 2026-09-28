<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\GameMode;
use PHPUnit\Framework\TestCase;

class GameModeTest extends TestCase
{
    public function testQcmIsEnabled(): void
    {
        self::assertTrue(GameMode::QCM->isEnabled());
    }

    public function testSurvivalIsEnabled(): void
    {
        self::assertTrue(GameMode::SURVIVAL->isEnabled());
    }

    public function testXpPerCorrect(): void
    {
        self::assertSame(3, GameMode::QCM->xpPerCorrect());
        self::assertSame(5, GameMode::SURVIVAL->xpPerCorrect());
        self::assertSame(4, GameMode::GUESS_WHO->xpPerCorrect());
    }

    public function testLabel(): void
    {
        self::assertSame('enum.game_mode.qcm.label', GameMode::QCM->label());
    }

    public function testRequiredLevel(): void
    {
        self::assertSame(1, GameMode::QCM->requiredLevel());
        self::assertSame(1, GameMode::HANGMAN->requiredLevel());
        self::assertSame(2, GameMode::GUESS_WHO->requiredLevel());
        self::assertSame(3, GameMode::INTRUS->requiredLevel());
        self::assertSame(5, GameMode::SURVIVAL->requiredLevel());
        self::assertSame(8, GameMode::CHRONO->requiredLevel());
    }

    public function testRequiredLevelNeverDecreasesWithPerceivedDifficulty(): void
    {
        $orderedByDifficulty = [
            GameMode::QCM,
            GameMode::HANGMAN,
            GameMode::GUESS_WHO,
            GameMode::INTRUS,
            GameMode::SURVIVAL,
            GameMode::CHRONO,
        ];

        $levels = array_map(static fn (GameMode $mode): int => $mode->requiredLevel(), $orderedByDifficulty);

        $sorted = $levels;
        sort($sorted);
        self::assertSame($sorted, $levels);
    }

    public function testIsUnlockedForLevelAtExactThreshold(): void
    {
        self::assertTrue(GameMode::CHRONO->isUnlockedForLevel(8));
    }

    public function testIsUnlockedForLevelAboveThreshold(): void
    {
        self::assertTrue(GameMode::CHRONO->isUnlockedForLevel(20));
    }

    public function testIsUnlockedForLevelBelowThreshold(): void
    {
        self::assertFalse(GameMode::CHRONO->isUnlockedForLevel(7));
    }
}
