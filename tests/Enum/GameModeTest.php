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

    public function testSurvivalIsNotEnabled(): void
    {
        self::assertFalse(GameMode::SURVIVAL->isEnabled());
    }

    public function testXpPerCorrect(): void
    {
        self::assertSame(3, GameMode::QCM->xpPerCorrect());
        self::assertSame(5, GameMode::SURVIVAL->xpPerCorrect());
        self::assertSame(4, GameMode::GUESS_WHO->xpPerCorrect());
    }

    public function testLabel(): void
    {
        self::assertSame('QCM - Mélange à trou', GameMode::QCM->label());
    }
}
