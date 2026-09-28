<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\SpicyMatchResult;
use PHPUnit\Framework\TestCase;

final class SpicyMatchResultTest extends TestCase
{
    private SpicyMatchResult $result;

    protected function setUp(): void
    {
        $this->result = new SpicyMatchResult();
    }

    public function testSetScoreWithinRange(): void
    {
        $this->result->setScore(75);
        self::assertSame(75, $this->result->getScore());
    }

    public function testSetScoreClampsAtZero(): void
    {
        $this->result->setScore(-10);
        self::assertSame(0, $this->result->getScore());
    }

    public function testSetScoreClampsAt100(): void
    {
        $this->result->setScore(150);
        self::assertSame(100, $this->result->getScore());
    }

    public function testSetScoreExactBoundaries(): void
    {
        $this->result->setScore(0);
        self::assertSame(0, $this->result->getScore());

        $this->result->setScore(100);
        self::assertSame(100, $this->result->getScore());
    }
}
