<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Strategy;

use App\Entity\UserProgression;
use App\Gamification\Strategy\EasterEggXpStrategy;
use PHPUnit\Framework\TestCase;

final class EasterEggXpStrategyTest extends TestCase
{
    private EasterEggXpStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new EasterEggXpStrategy();
    }

    public function testSupportsEasterEggFoundEvent(): void
    {
        self::assertTrue($this->strategy->supports('easter_egg_found'));
    }

    public function testDoesNotSupportOtherEvents(): void
    {
        self::assertFalse($this->strategy->supports('match_saved'));
        self::assertFalse($this->strategy->supports('spice_read'));
        self::assertFalse($this->strategy->supports(''));
    }

    public function testCalculateReturnsXpAmountFromContext(): void
    {
        $progression = new UserProgression();
        self::assertSame(100, $this->strategy->calculate($progression, [
            'xpAmount' => 100,
        ]));
    }

    public function testCalculateReturnsDefaultXpWhenAmountMissing(): void
    {
        $progression = new UserProgression();
        self::assertSame(75, $this->strategy->calculate($progression, []));
    }

    public function testCalculateReturnsDefaultXpWhenAmountIsNull(): void
    {
        $progression = new UserProgression();
        self::assertSame(75, $this->strategy->calculate($progression, [
            'xpAmount' => null,
        ]));
    }
}
