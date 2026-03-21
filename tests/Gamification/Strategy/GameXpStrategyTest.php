<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Strategy;

use App\Entity\UserProgression;
use App\Gamification\Strategy\GameXpStrategy;
use PHPUnit\Framework\TestCase;

class GameXpStrategyTest extends TestCase
{
    private GameXpStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new GameXpStrategy();
    }

    public function testSupportsGameCompleted(): void
    {
        self::assertTrue($this->strategy->supports('game_completed'));
    }

    public function testDoesNotSupportOtherEvents(): void
    {
        self::assertFalse($this->strategy->supports('match_saved'));
        self::assertFalse($this->strategy->supports('spice_read'));
    }

    public function testCalculateReturnsXpFromContext(): void
    {
        $progression = new UserProgression();
        $xp = $this->strategy->calculate($progression, [
            'xpEarned' => 45,
        ]);

        self::assertSame(45, $xp);
    }

    public function testCalculateReturnsZeroWhenNoContext(): void
    {
        $progression = new UserProgression();
        $xp = $this->strategy->calculate($progression, []);

        self::assertSame(0, $xp);
    }
}
