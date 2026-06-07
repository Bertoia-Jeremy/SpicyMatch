<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\GameDifficulty;
use PHPUnit\Framework\TestCase;

class GameDifficultyTest extends TestCase
{
    public function testXpMultipliers(): void
    {
        self::assertSame(1.0, GameDifficulty::EASY->xpMultiplier());
        self::assertSame(1.5, GameDifficulty::MEDIUM->xpMultiplier());
        self::assertSame(2.0, GameDifficulty::HARD->xpMultiplier());
    }

    public function testLabels(): void
    {
        self::assertSame('enum.difficulty.easy', GameDifficulty::EASY->label());
        self::assertSame('enum.difficulty.medium', GameDifficulty::MEDIUM->label());
        self::assertSame('enum.difficulty.hard', GameDifficulty::HARD->label());
    }
}
