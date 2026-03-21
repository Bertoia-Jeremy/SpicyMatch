<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use App\Entity\SpicyMatchResult;
use App\Entity\Users;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SpicyMatchTest extends TestCase
{
    private SpicyMatch $match;

    protected function setUp(): void
    {
        $this->match = new SpicyMatch();
    }

    // ── isManual ────────────────────────────────────────────────────────────

    public function testIsManualDefaultsToFalse(): void
    {
        self::assertFalse($this->match->isManual());
    }

    public function testSetIsManualToTrue(): void
    {
        $result = $this->match->setIsManual(true);

        self::assertTrue($this->match->isManual());
        self::assertSame($this->match, $result, 'setIsManual() should be fluent');
    }

    public function testSetIsManualToggle(): void
    {
        $this->match->setIsManual(true);
        self::assertTrue($this->match->isManual());

        $this->match->setIsManual(false);
        self::assertFalse($this->match->isManual());
    }

    // ── Constructor defaults ────────────────────────────────────────────────

    public function testConstructorInitializesEmptyCollections(): void
    {
        self::assertCount(0, $this->match->getSpices());
        self::assertCount(0, $this->match->getResults());
        self::assertCount(0, $this->match->getSpicyMatchHistories());
    }

    public function testConstructorInitializesTimestamps(): void
    {
        self::assertInstanceOf(\DateTimeImmutable::class, $this->match->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $this->match->getUpdatedAt());
        self::assertNull($this->match->getDeletedAt());
    }

    // ── User ────────────────────────────────────────────────────────────────

    public function testUserIsNullByDefault(): void
    {
        self::assertNull($this->match->getUser());
    }

    public function testSetUser(): void
    {
        $user = $this->createMock(Users::class);
        $this->match->setUser($user);

        self::assertSame($user, $this->match->getUser());
    }

    // ── Spices collection ───────────────────────────────────────────────────

    public function testAddSpice(): void
    {
        $spice = $this->createMock(Spices::class);
        $result = $this->match->addSpice($spice);

        self::assertCount(1, $this->match->getSpices());
        self::assertSame($this->match, $result);
    }

    public function testAddSpiceIgnoresDuplicate(): void
    {
        $spice = $this->createMock(Spices::class);
        $this->match->addSpice($spice);
        $this->match->addSpice($spice);

        self::assertCount(1, $this->match->getSpices());
    }

    public function testRemoveSpice(): void
    {
        $spice = $this->createMock(Spices::class);
        $this->match->addSpice($spice);
        $this->match->removeSpice($spice);

        self::assertCount(0, $this->match->getSpices());
    }

    public function testGetSpiceCount(): void
    {
        $s1 = $this->createMock(Spices::class);
        $s2 = $this->createMock(Spices::class);
        $this->match->addSpice($s1);
        $this->match->addSpice($s2);

        self::assertSame(2, $this->match->getSpiceCount());
    }

    // ── Results collection ──────────────────────────────────────────────────

    public function testAddResultSetsSpicyMatch(): void
    {
        $result = new SpicyMatchResult();
        $this->match->addResult($result);

        self::assertCount(1, $this->match->getResults());
        self::assertSame($this->match, $result->getSpicyMatch());
    }

    public function testAddResultIgnoresDuplicate(): void
    {
        $result = new SpicyMatchResult();
        $this->match->addResult($result);
        $this->match->addResult($result);

        self::assertCount(1, $this->match->getResults());
    }

    public function testManualMatchHasNoResults(): void
    {
        $this->match->setIsManual(true);

        self::assertTrue($this->match->isManual());
        self::assertCount(0, $this->match->getResults());
    }

    // ── Timestamps ──────────────────────────────────────────────────────────

    public function testSetDeletedAt(): void
    {
        $now = new \DateTimeImmutable();
        $this->match->setDeletedAt($now);

        self::assertSame($now, $this->match->getDeletedAt());
    }

    public function testSetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2025-01-01');
        $this->match->setCreatedAt($date);

        self::assertSame($date, $this->match->getCreatedAt());
    }

    public function testSetUpdatedAt(): void
    {
        $date = new \DateTimeImmutable('2025-06-15');
        $this->match->setUpdatedAt($date);

        self::assertSame($date, $this->match->getUpdatedAt());
    }
}
