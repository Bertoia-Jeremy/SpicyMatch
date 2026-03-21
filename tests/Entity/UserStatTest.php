<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\UserStat;
use PHPUnit\Framework\TestCase;

final class UserStatTest extends TestCase
{
    public function testTotalActionsHook(): void
    {
        $stats = new UserStat();
        $stats->setTotalMatches(5);
        $stats->incrementSpicesRead(); // 1
        $stats->incrementEasterEggsFound(); // 1

        self::assertSame(7, $stats->totalActions);
    }

    public function testVisitedGroupsCountHook(): void
    {
        $stats = new UserStat();
        $stats->addVisitedAromaticGroup(1);
        $stats->addVisitedAromaticGroup(2);
        $stats->addVisitedAromaticGroup(1); // Duplicate

        self::assertSame(2, $stats->visitedGroupsCount);
    }

    public function testRecordVisitedSpiceAddsToList(): void
    {
        $stats = new UserStat();
        $stats->recordVisitedSpice(42);
        self::assertContains(42, $stats->getLastVisitedSpices());
    }

    public function testRecordVisitedSpiceKeepsLastTen(): void
    {
        $stats = new UserStat();
        for ($i = 1; $i <= 12; ++$i) {
            $stats->recordVisitedSpice($i);
        }

        $spices = $stats->getLastVisitedSpices();
        self::assertCount(10, $spices);
        // First two (1, 2) should be evicted
        self::assertNotContains(1, $spices);
        self::assertNotContains(2, $spices);
        self::assertContains(12, $spices);
    }

    public function testIncrementTotalMatches(): void
    {
        $stats = new UserStat();
        $stats->setTotalMatches(0);
        $stats->setTotalMatches(1);
        self::assertSame(1, $stats->getTotalMatches());
    }

    public function testIncrementEasterEggsFound(): void
    {
        $stats = new UserStat();
        $stats->incrementEasterEggsFound();
        self::assertSame(1, $stats->getEasterEggsFound());
    }

    public function testAddVisitedAromaticGroupNewGroup(): void
    {
        $stats = new UserStat();
        $stats->addVisitedAromaticGroup(5);
        self::assertContains(5, $stats->getVisitedAromaticGroups());
        self::assertSame(1, $stats->visitedGroupsCount);
    }

    public function testAddVisitedAromaticGroupAlreadyVisited(): void
    {
        $stats = new UserStat();
        $stats->addVisitedAromaticGroup(5);
        $stats->addVisitedAromaticGroup(5);
        self::assertSame(1, $stats->visitedGroupsCount);
    }

    public function testIncrementSpicesRead(): void
    {
        $stats = new UserStat();
        $stats->incrementSpicesRead();
        self::assertSame(1, $stats->getTotalSpicesRead());
    }

    public function testTotalActionsIsSumOfAllCounters(): void
    {
        $stats = new UserStat();
        $stats->setTotalMatches(5);
        for ($i = 0; $i < 3; ++$i) {
            $stats->incrementSpicesRead();
        }
        $stats->incrementEasterEggsFound();
        $stats->incrementEasterEggsFound();

        // totalMatches(5) + totalSpicesRead(3) + easterEggsFound(2) = 10
        self::assertSame(10, $stats->totalActions);
    }
}
