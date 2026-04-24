<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Entity\UserStat;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Gamification\Evaluator\AllTerpenesVisitedEvaluator;
use App\Repository\AromaticGroupsRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class AllTerpenesVisitedEvaluatorTest extends TestCase
{
    private AromaticGroupsRepository&MockObject $aromaticGroupsRepo;
    private AllTerpenesVisitedEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->aromaticGroupsRepo = $this->createMock(AromaticGroupsRepository::class);
        $this->evaluator = new AllTerpenesVisitedEvaluator($this->aromaticGroupsRepo);
    }

    public function testTriggerAndEventType(): void
    {
        self::assertSame(AchievementTrigger::ALL_TERPENES_VISITED, $this->evaluator->trigger());
        self::assertSame(['spice_read'], $this->evaluator->eventTypes());
    }

    public function testCurrentValueReturnsZeroWhenStatsMissing(): void
    {
        $progression = new UserProgression();
        self::assertSame(0, $this->evaluator->currentValue($progression, []));
    }

    public function testCurrentValueReturnsVisitedGroupsCount(): void
    {
        $stats = new UserStat();
        $stats->addVisitedAromaticGroup(1);
        $stats->addVisitedAromaticGroup(2);

        $user = $this->createMock(Users::class);
        $user->method('getStats')
            ->willReturn($stats);

        $progression = new UserProgression();
        $progression->setUser($user);

        self::assertSame(2, $this->evaluator->currentValue($progression, []));
    }

    public function testReturnsFalseWhenStatsMissing(): void
    {
        $progression = new UserProgression();
        self::assertFalse($this->evaluator->isMet($this->makeAchievement(), $progression, []));
    }

    public function testReturnsFalseWhenZeroGroups(): void
    {
        $stats = new UserStat();
        $user = $this->createMock(Users::class);
        $user->method('getStats')
            ->willReturn($stats);

        $progression = new UserProgression();
        $progression->setUser($user);

        $this->aromaticGroupsRepo->method('count')
            ->willReturn(0);

        self::assertFalse($this->evaluator->isMet($this->makeAchievement(), $progression, []));
    }

    public function testUnlocksWhenAllGroupsVisited(): void
    {
        $stats = new UserStat();
        $stats->addVisitedAromaticGroup(1);
        $stats->addVisitedAromaticGroup(2);
        $stats->addVisitedAromaticGroup(3);

        $user = $this->createMock(Users::class);
        $user->method('getStats')
            ->willReturn($stats);

        $progression = new UserProgression();
        $progression->setUser($user);

        $this->aromaticGroupsRepo->method('count')
            ->willReturn(3);

        self::assertTrue($this->evaluator->isMet($this->makeAchievement(), $progression, []));
    }

    public function testDoesNotUnlockWhenNotAllVisited(): void
    {
        $stats = new UserStat();
        $stats->addVisitedAromaticGroup(1);

        $user = $this->createMock(Users::class);
        $user->method('getStats')
            ->willReturn($stats);

        $progression = new UserProgression();
        $progression->setUser($user);

        $this->aromaticGroupsRepo->method('count')
            ->willReturn(3);

        self::assertFalse($this->evaluator->isMet($this->makeAchievement(), $progression, []));
    }

    private function makeAchievement(): Achievement
    {
        return (new Achievement())
            ->setSlug('test-all-terpenes')
            ->setName('Test')
            ->setDescription('d')
            ->setTrigger(AchievementTrigger::ALL_TERPENES_VISITED)
            ->setTriggerValue(1)
            ->setXpReward(10)
            ->setRarity(AchievementRarity::EPIC);
    }
}
