<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Entity\UserStat;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Gamification\Evaluator\AromaticGroupsVisitedEvaluator;
use App\Repository\AromaticGroupsRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class AromaticGroupsVisitedEvaluatorTest extends TestCase
{
    private AromaticGroupsRepository&MockObject $aromaticGroupsRepo;

    private AromaticGroupsVisitedEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->aromaticGroupsRepo = $this->createMock(AromaticGroupsRepository::class);
        $this->evaluator = new AromaticGroupsVisitedEvaluator($this->aromaticGroupsRepo);
    }

    public function testTriggerAndEventType(): void
    {
        self::assertSame(AchievementTrigger::AROMATIC_GROUPS_VISITED, $this->evaluator->trigger());
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

    /**
     * @return iterable<string, array{0: int, 1: int, 2: int, 3: bool}>
     */
    public static function thresholdProvider(): iterable
    {
        yield 'apprentice reached' => [3, 7, 3, true];
        yield 'apprentice not reached' => [2, 7, 3, false];
        yield 'all groups visited' => [7, 7, 7, true];
        yield 'one group missing' => [6, 7, 7, false];
        yield 'threshold above catalogue capped to total' => [5, 5, 7, true];
        yield 'zero threshold means one group' => [1, 7, 0, true];
    }

    #[DataProvider('thresholdProvider')]
    public function testIsMetAgainstThreshold(int $visited, int $totalGroups, int $triggerValue, bool $expected): void
    {
        $stats = new UserStat();
        for ($id = 1; $id <= $visited; ++$id) {
            $stats->addVisitedAromaticGroup($id);
        }

        $user = $this->createStub(Users::class);
        $user->method('getStats')
            ->willReturn($stats);

        $progression = new UserProgression();
        $progression->setUser($user);

        $this->aromaticGroupsRepo->method('count')
            ->willReturn($totalGroups);

        self::assertSame($expected, $this->evaluator->isMet($this->makeAchievement($triggerValue), $progression, []));
    }

    private function makeAchievement(int $triggerValue = 1): Achievement
    {
        return new Achievement()
            ->setSlug('test-aromatic-groups')
            ->setName('Test')
            ->setDescription('d')
            ->setTrigger(AchievementTrigger::AROMATIC_GROUPS_VISITED)
            ->setTriggerValue($triggerValue)
            ->setXpReward(10)
            ->setRarity(AchievementRarity::EPIC);
    }
}
