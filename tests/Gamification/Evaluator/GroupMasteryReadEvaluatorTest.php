<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\AromaticGroups;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Gamification\Evaluator\GroupMasteryReadEvaluator;
use App\Repository\SpiceViewRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class GroupMasteryReadEvaluatorTest extends TestCase
{
    private SpiceViewRepository&MockObject $spiceViewRepo;
    private GroupMasteryReadEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->spiceViewRepo = $this->createMock(SpiceViewRepository::class);
        $this->evaluator = new GroupMasteryReadEvaluator($this->spiceViewRepo);
    }

    public function testTriggerAndEventType(): void
    {
        self::assertSame(AchievementTrigger::GROUP_MASTERY_READ, $this->evaluator->trigger());
        self::assertSame(['spice_read'], $this->evaluator->eventTypes());
    }

    public function testReturnsFalseWhenUserNull(): void
    {
        $group = $this->createStub(AromaticGroups::class);
        $achievement = $this->makeAchievement($group, 5);
        $progression = new UserProgression();

        self::assertFalse($this->evaluator->isMet($achievement, $progression, []));
    }

    public function testReturnsFalseWhenGroupNull(): void
    {
        $achievement = $this->makeAchievement(null, 5);
        $progression = $this->progressionWithUser();

        self::assertFalse($this->evaluator->isMet($achievement, $progression, []));
    }

    public function testThresholdReachedUnlocks(): void
    {
        $group = $this->createStub(AromaticGroups::class);
        $achievement = $this->makeAchievement($group, 5);
        $progression = $this->progressionWithUser();

        $this->spiceViewRepo->method('countByGroup')
            ->willReturn(5);
        self::assertTrue($this->evaluator->isMet($achievement, $progression, []));
    }

    public function testBelowThresholdReturnsFalse(): void
    {
        $group = $this->createStub(AromaticGroups::class);
        $achievement = $this->makeAchievement($group, 10);
        $progression = $this->progressionWithUser();

        $this->spiceViewRepo->method('countByGroup')
            ->willReturn(4);
        self::assertFalse($this->evaluator->isMet($achievement, $progression, []));
    }

    private function makeAchievement(?AromaticGroups $group, int $triggerValue): Achievement
    {
        $a = new Achievement();
        $a->setSlug('test-group-mastery')
            ->setName('Test')
            ->setDescription('d')
            ->setTrigger(AchievementTrigger::GROUP_MASTERY_READ)
            ->setTriggerValue($triggerValue)
            ->setXpReward(10)
            ->setRarity(AchievementRarity::RARE);
        if ($group !== null) {
            $a->setContextAromaticGroup($group);
        }

        return $a;
    }

    private function progressionWithUser(): UserProgression
    {
        $user = $this->createMock(Users::class);
        $user->method('getId')
            ->willReturn(1);
        $progression = new UserProgression();
        $progression->setUser($user);

        return $progression;
    }
}
