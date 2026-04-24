<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Entity\UserStat;
use PHPUnit\Framework\TestCase;

final class UserStatTest extends TestCase
{
    public function testTotalActionsHookReadsFromProgression(): void
    {
        $progression = new UserProgression();
        (new \ReflectionProperty(UserProgression::class, 'totalMatches'))->setValue($progression, 5);
        (new \ReflectionProperty(UserProgression::class, 'totalSpicesRead'))->setValue($progression, 1);

        $user = new Users();
        $user->setProgression($progression);

        $stats = new UserStat();
        $stats->setUser($user);
        $stats->incrementEasterEggsFound(); // 1

        self::assertSame(7, $stats->totalActions);
    }

    public function testTotalActionsHookWithNoProgressionIsZeroPlusEasterEggs(): void
    {
        $user = new Users();
        $stats = new UserStat();
        $stats->setUser($user);
        $stats->incrementEasterEggsFound();

        self::assertSame(1, $stats->totalActions);
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

    public function testRecordFoundEggIsIdempotent(): void
    {
        $stats = new UserStat();
        $stats->recordFoundEgg('grain_de_sel');
        $stats->recordFoundEgg('grain_de_sel');

        self::assertSame(['grain_de_sel'], $stats->getFoundEggSlugs());
        self::assertTrue($stats->hasFoundEgg('grain_de_sel'));
    }
}
