<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
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

    public function testDefaultValues(): void
    {
        self::assertNull($this->history->getTitle());
        self::assertFalse($this->history->isFavorite());
        self::assertNull($this->history->getDeletedAt());
        self::assertCount(0, $this->history->getPreparationTips());
        self::assertCount(0, $this->history->getCookingTips());
    }

    public function testSetTitleNull(): void
    {
        $this->history->setTitle('Test');
        $this->history->setTitle(null);
        self::assertNull($this->history->getTitle());
    }

    public function testSetFavorite(): void
    {
        $this->history->setFavorite(true);
        self::assertTrue($this->history->isFavorite());

        $this->history->setFavorite(false);
        self::assertFalse($this->history->isFavorite());
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
}
