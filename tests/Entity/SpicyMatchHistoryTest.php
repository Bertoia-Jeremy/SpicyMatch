<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SpicyMatchHistoryTest extends TestCase
{
    private SpicyMatchHistory $history;

    protected function setUp(): void
    {
        $this->history = new SpicyMatchHistory();
    }

    // ── Constructor defaults ────────────────────────────────────────────────

    public function testDefaultValues(): void
    {
        self::assertNull($this->history->getTitle());
        self::assertFalse($this->history->isFavorite());
        self::assertNull($this->history->getDeletedAt());
        self::assertCount(0, $this->history->getPreparationTips());
        self::assertCount(0, $this->history->getCookingTips());
    }

    public function testTimestampsInitializedOnConstruct(): void
    {
        self::assertInstanceOf(\DateTimeImmutable::class, $this->history->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $this->history->getUpdatedAt());
    }

    // ── SpicyMatch relationship ─────────────────────────────────────────────

    public function testSetSpicyMatch(): void
    {
        $match = new SpicyMatch();
        $result = $this->history->setSpicyMatch($match);

        self::assertSame($match, $this->history->getSpicyMatch());
        self::assertSame($this->history, $result);
    }

    public function testHistoryLinkedToManualMatch(): void
    {
        $match = new SpicyMatch();
        $match->setIsManual(true);
        $this->history->setSpicyMatch($match);

        self::assertTrue($this->history->getSpicyMatch()->isManual());
    }

    public function testHistoryLinkedToAutoMatch(): void
    {
        $match = new SpicyMatch();
        $match->setIsManual(false);
        $this->history->setSpicyMatch($match);

        self::assertFalse($this->history->getSpicyMatch()->isManual());
    }

    // ── Title ───────────────────────────────────────────────────────────────

    public function testSetTitle(): void
    {
        $this->history->setTitle('Mon mélange épicé');
        self::assertSame('Mon mélange épicé', $this->history->getTitle());
    }

    public function testSetTitleNull(): void
    {
        $this->history->setTitle('Test');
        $this->history->setTitle(null);
        self::assertNull($this->history->getTitle());
    }

    // ── Favorite ────────────────────────────────────────────────────────────

    public function testSetFavorite(): void
    {
        $this->history->setFavorite(true);
        self::assertTrue($this->history->isFavorite());

        $this->history->setFavorite(false);
        self::assertFalse($this->history->isFavorite());
    }

    public function testManualMatchCanBeFavorited(): void
    {
        $match = new SpicyMatch();
        $match->setIsManual(true);
        $this->history->setSpicyMatch($match);
        $this->history->setFavorite(true);

        self::assertTrue($this->history->isFavorite());
        self::assertTrue($this->history->getSpicyMatch()->isManual());
    }

    // ── PreparationTips collection ──────────────────────────────────────────

    public function testAddPreparationTip(): void
    {
        $tip = $this->createMock(PreparationTips::class);
        $result = $this->history->addPreparationTip($tip);

        self::assertCount(1, $this->history->getPreparationTips());
        self::assertSame($this->history, $result);
    }

    public function testAddPreparationTipIgnoresDuplicate(): void
    {
        $tip = $this->createMock(PreparationTips::class);
        $this->history->addPreparationTip($tip);
        $this->history->addPreparationTip($tip);

        self::assertCount(1, $this->history->getPreparationTips());
    }

    public function testRemovePreparationTip(): void
    {
        $tip = $this->createMock(PreparationTips::class);
        $this->history->addPreparationTip($tip);
        $this->history->removePreparationTip($tip);

        self::assertCount(0, $this->history->getPreparationTips());
    }

    // ── CookingTips collection ──────────────────────────────────────────────

    public function testAddCookingTip(): void
    {
        $tip = $this->createMock(CookingTips::class);
        $result = $this->history->addCookingTip($tip);

        self::assertCount(1, $this->history->getCookingTips());
        self::assertSame($this->history, $result);
    }

    public function testAddCookingTipIgnoresDuplicate(): void
    {
        $tip = $this->createMock(CookingTips::class);
        $this->history->addCookingTip($tip);
        $this->history->addCookingTip($tip);

        self::assertCount(1, $this->history->getCookingTips());
    }

    public function testRemoveCookingTip(): void
    {
        $tip = $this->createMock(CookingTips::class);
        $this->history->addCookingTip($tip);
        $this->history->removeCookingTip($tip);

        self::assertCount(0, $this->history->getCookingTips());
    }

    // ── Timestamps ──────────────────────────────────────────────────────────

    public function testSetDeletedAt(): void
    {
        $now = new \DateTimeImmutable();
        $this->history->setDeletedAt($now);
        self::assertSame($now, $this->history->getDeletedAt());
    }

    public function testSetUpdatedAt(): void
    {
        $date = new \DateTimeImmutable('2025-06-15');
        $this->history->setUpdatedAt($date);
        self::assertSame($date, $this->history->getUpdatedAt());
    }

    // ── Manual match edge cases ─────────────────────────────────────────────

    public function testManualMatchHistoryHasNoResultsOnParent(): void
    {
        $match = new SpicyMatch();
        $match->setIsManual(true);
        $this->history->setSpicyMatch($match);

        self::assertCount(0, $this->history->getSpicyMatch()->getResults());
    }
}
