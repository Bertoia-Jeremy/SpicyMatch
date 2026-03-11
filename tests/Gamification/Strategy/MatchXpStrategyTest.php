<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Strategy;

use App\Entity\UserProgression;
use App\Gamification\Strategy\MatchXpStrategy;
use PHPUnit\Framework\TestCase;

final class MatchXpStrategyTest extends TestCase
{
    private MatchXpStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new MatchXpStrategy();
    }

    public function testSupportsMatchSavedEvent(): void
    {
        self::assertTrue($this->strategy->supports('match_saved'));
    }

    public function testDoesNotSupportOtherEvents(): void
    {
        self::assertFalse($this->strategy->supports('spice_read'));
        self::assertFalse($this->strategy->supports('easter_egg_found'));
        self::assertFalse($this->strategy->supports('favorite_toggled'));
        self::assertFalse($this->strategy->supports(''));
    }

    public function testCalculateAlwaysReturnsTenXp(): void
    {
        $progression = new UserProgression();
        self::assertSame(10, $this->strategy->calculate($progression, []));
    }

    public function testCalculateIgnoresContext(): void
    {
        $progression = new UserProgression();
        self::assertSame(10, $this->strategy->calculate($progression, ['isNewView' => true, 'xpAmount' => 999]));
    }
}
