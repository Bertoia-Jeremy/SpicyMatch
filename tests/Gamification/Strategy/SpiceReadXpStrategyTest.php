<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Strategy;

use App\Entity\UserProgression;
use App\Gamification\Strategy\SpiceReadXpStrategy;
use PHPUnit\Framework\TestCase;

final class SpiceReadXpStrategyTest extends TestCase
{
    private SpiceReadXpStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new SpiceReadXpStrategy();
    }

    public function testSupportsSpiceReadEvent(): void
    {
        self::assertTrue($this->strategy->supports('spice_read'));
    }

    public function testDoesNotSupportOtherEvents(): void
    {
        self::assertFalse($this->strategy->supports('match_saved'));
        self::assertFalse($this->strategy->supports('easter_egg_found'));
        self::assertFalse($this->strategy->supports(''));
    }

    public function testCalculateReturnsFiveXpOnNewView(): void
    {
        $progression = new UserProgression();
        self::assertSame(5, $this->strategy->calculate($progression, ['isNewView' => true]));
    }

    public function testCalculateReturnsZeroXpWhenNotNewView(): void
    {
        $progression = new UserProgression();
        self::assertSame(0, $this->strategy->calculate($progression, ['isNewView' => false]));
    }

    public function testCalculateReturnsZeroXpWhenIsNewViewMissing(): void
    {
        $progression = new UserProgression();
        self::assertSame(0, $this->strategy->calculate($progression, []));
    }
}
