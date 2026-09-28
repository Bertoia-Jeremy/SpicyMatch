<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CookingTips;
use App\Entity\PreparationTips;
use App\Entity\Spices;
use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $tip = $this->createStub(PreparationTips::class);
        $this->history->addPreparationTip($tip);
        $this->history->addPreparationTip($tip);

        self::assertCount(1, $this->history->getPreparationTips());
    }

    public function testRemovePreparationTip(): void
    {
        $tip = $this->createStub(PreparationTips::class);
        $this->history->addPreparationTip($tip);
        $this->history->removePreparationTip($tip);

        self::assertCount(0, $this->history->getPreparationTips());
    }

    public function testAddCookingTipIgnoresDuplicate(): void
    {
        $tip = $this->createStub(CookingTips::class);
        $this->history->addCookingTip($tip);
        $this->history->addCookingTip($tip);

        self::assertCount(1, $this->history->getCookingTips());
    }

    public function testRemoveCookingTip(): void
    {
        $tip = $this->createStub(CookingTips::class);
        $this->history->addCookingTip($tip);
        $this->history->removeCookingTip($tip);

        self::assertCount(0, $this->history->getCookingTips());
    }

    /**
     * @param list<string> $cooked
     * @param list<string> $prepared
     */
    #[DataProvider('sealedCases')]
    public function testIsSealedRequiresBothTipsForEverySpice(array $cooked, array $prepared, bool $expected): void
    {
        $spices = [
            'a' => new Spices(),
            'b' => new Spices(),
        ];
        $match = new SpicyMatch();
        foreach ($spices as $spice) {
            $match->addSpice($spice);
        }
        $this->history->setSpicyMatch($match);

        foreach ($cooked as $key) {
            $this->history->addCookingTip(new CookingTips()->setSpice($spices[$key]));
        }
        foreach ($prepared as $key) {
            $this->history->addPreparationTip(new PreparationTips()->setSpice($spices[$key]));
        }

        self::assertSame($expected, $this->history->isSealed());
    }

    /**
     * @return iterable<string, array{list<string>, list<string>, bool}>
     */
    public static function sealedCases(): iterable
    {
        yield 'nothing chosen' => [[], [], false];
        yield 'cooking only for every spice' => [['a', 'b'], [], false];
        yield 'one spice missing its preparation' => [['a', 'b'], ['a'], false];
        yield 'tips on the same spice only' => [['a', 'a'], ['a', 'a'], false];
        yield 'every spice sealed' => [['a', 'b'], ['b', 'a'], true];
    }

    public function testIsSealedIsFalseWithoutSpices(): void
    {
        $this->history->setSpicyMatch(new SpicyMatch());

        self::assertFalse($this->history->isSealed());
    }

    public function testChooseCookingTipReplacesOnlyTheSameSpiceAndClearsWithNull(): void
    {
        $a = new Spices();
        $b = new Spices();
        $first = new CookingTips()
            ->setSpice($a);
        $second = new CookingTips()
            ->setSpice($a);
        $other = new CookingTips()
            ->setSpice($b);

        $this->history->chooseCookingTip($a, $first);
        $this->history->chooseCookingTip($b, $other);
        $this->history->chooseCookingTip($a, $second);
        $this->history->chooseCookingTip($a, $second);

        self::assertSame([$other, $second], array_values($this->history->getCookingTips()->toArray()));

        $this->history->chooseCookingTip($a, null);

        self::assertSame([$other], array_values($this->history->getCookingTips()->toArray()));
    }

    public function testChoosePreparationTipReplacesOnlyTheSameSpiceAndClearsWithNull(): void
    {
        $a = new Spices();
        $b = new Spices();
        $first = new PreparationTips()
            ->setSpice($a);
        $second = new PreparationTips()
            ->setSpice($a);
        $other = new PreparationTips()
            ->setSpice($b);

        $this->history->choosePreparationTip($a, $first);
        $this->history->choosePreparationTip($b, $other);
        $this->history->choosePreparationTip($a, $second);

        self::assertSame([$other, $second], array_values($this->history->getPreparationTips()->toArray()));

        $this->history->choosePreparationTip($a, null);

        self::assertSame([$other], array_values($this->history->getPreparationTips()->toArray()));
    }

    public function testChooseRejectsATipOfAnotherSpice(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->history->chooseCookingTip(new Spices(), new CookingTips()->setSpice(new Spices()));
    }

    public function testMarkSealedIfCompleteFiresOnlyOnTheFirstTransition(): void
    {
        $spice = new Spices();
        $match = new SpicyMatch();
        $match->addSpice($spice);
        $this->history->setSpicyMatch($match);
        $first = new \DateTimeImmutable('2026-09-28 10:00');

        self::assertFalse($this->history->markSealedIfComplete($first));
        self::assertNull($this->history->getSealedAt());

        $this->history->chooseCookingTip($spice, new CookingTips()->setSpice($spice));
        $this->history->choosePreparationTip($spice, new PreparationTips()->setSpice($spice));

        self::assertTrue($this->history->markSealedIfComplete($first));

        $this->history->chooseCookingTip($spice, null);
        $this->history->chooseCookingTip($spice, new CookingTips()->setSpice($spice));

        self::assertFalse($this->history->markSealedIfComplete(new \DateTimeImmutable('2026-09-28 11:00')));
        self::assertEquals($first, $this->history->getSealedAt());
    }
}
