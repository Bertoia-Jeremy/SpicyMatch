<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchResult;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SpicyMatchResultTest extends TestCase
{
    private SpicyMatchResult $result;

    protected function setUp(): void
    {
        $this->result = new SpicyMatchResult();
    }

    // ── Defaults ────────────────────────────────────────────────────────────

    public function testDefaultScoreIsZero(): void
    {
        self::assertSame(0, $this->result->getScore());
    }

    public function testDefaultCountsAreZero(): void
    {
        self::assertSame(0, $this->result->getMainCompoundsCount());
        self::assertSame(0, $this->result->getSecondaryCompoundsCount());
        self::assertSame(0, $this->result->getAlchemyFlavorsCount());
    }

    // ── Score clamping ──────────────────────────────────────────────────────

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

    // ── Fluent setters ──────────────────────────────────────────────────────

    public function testSetScoreIsFluent(): void
    {
        $returned = $this->result->setScore(50);
        self::assertSame($this->result, $returned);
    }

    public function testSetSpiceIsFluent(): void
    {
        $spice = $this->createMock(Spices::class);
        $returned = $this->result->setSpice($spice);
        self::assertSame($this->result, $returned);
    }

    public function testSetSpicyMatchIsFluent(): void
    {
        $match = new SpicyMatch();
        $returned = $this->result->setSpicyMatch($match);
        self::assertSame($this->result, $returned);
    }

    // ── Relationships ───────────────────────────────────────────────────────

    public function testSpiceAssociation(): void
    {
        $spice = $this->createMock(Spices::class);
        $this->result->setSpice($spice);
        self::assertSame($spice, $this->result->getSpice());
    }

    public function testSpicyMatchAssociation(): void
    {
        $match = new SpicyMatch();
        $this->result->setSpicyMatch($match);
        self::assertSame($match, $this->result->getSpicyMatch());
    }

    // ── Compound counts ─────────────────────────────────────────────────────

    public function testSetMainCompoundsCount(): void
    {
        $returned = $this->result->setMainCompoundsCount(3);
        self::assertSame(3, $this->result->getMainCompoundsCount());
        self::assertSame($this->result, $returned);
    }

    public function testSetSecondaryCompoundsCount(): void
    {
        $returned = $this->result->setSecondaryCompoundsCount(5);
        self::assertSame(5, $this->result->getSecondaryCompoundsCount());
        self::assertSame($this->result, $returned);
    }

    public function testSetAlchemyFlavorsCount(): void
    {
        $returned = $this->result->setAlchemyFlavorsCount(2);
        self::assertSame(2, $this->result->getAlchemyFlavorsCount());
        self::assertSame($this->result, $returned);
    }

    // ── Manual match context ────────────────────────────────────────────────

    public function testResultWithZeroScoreForManualMatch(): void
    {
        $match = new SpicyMatch();
        $match->setIsManual(true);

        $this->result->setSpicyMatch($match);
        // En mode manuel, le score reste à 0 (pas de calcul de compatibilité)
        self::assertSame(0, $this->result->getScore());
        self::assertTrue($match->isManual());
    }
}
