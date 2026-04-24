<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Gamification\Evaluator\AllPreparationMethodsReadEvaluator;
use App\Repository\PreparationMethodsRepository;
use App\Repository\SpiceViewRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class AllPreparationMethodsReadEvaluatorTest extends TestCase
{
    private PreparationMethodsRepository&MockObject $prepRepo;
    private SpiceViewRepository&MockObject $spiceViewRepo;
    private AllPreparationMethodsReadEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->prepRepo = $this->createMock(PreparationMethodsRepository::class);
        $this->spiceViewRepo = $this->createMock(SpiceViewRepository::class);
        $this->evaluator = new AllPreparationMethodsReadEvaluator($this->prepRepo, $this->spiceViewRepo);
    }

    public function testTriggerAndEventType(): void
    {
        self::assertSame(AchievementTrigger::ALL_PREPARATION_METHODS_READ, $this->evaluator->trigger());
        self::assertSame(['spice_read'], $this->evaluator->eventTypes());
    }

    public function testReturnsFalseWhenUserNull(): void
    {
        $progression = new UserProgression();
        self::assertFalse($this->evaluator->isMet($this->makeAchievement(), $progression, []));
    }

    public function testReturnsFalseWhenZeroTotalMethods(): void
    {
        // Edge case: DB empty → we must not unlock accidentally (0 >= 0 is true!)
        $progression = $this->progressionWithUser();
        $this->prepRepo->method('count')
            ->willReturn(0);

        self::assertFalse($this->evaluator->isMet($this->makeAchievement(), $progression, []));
    }

    public function testUnlocksWhenUserHasSeenAllMethods(): void
    {
        $progression = $this->progressionWithUser();
        $this->prepRepo->method('count')
            ->willReturn(5);
        $this->spiceViewRepo->method('countDistinctPreparationMethodsSeenBy')
            ->willReturn(5);

        self::assertTrue($this->evaluator->isMet($this->makeAchievement(), $progression, []));
    }

    public function testDoesNotUnlockIfMissingAtLeastOneMethod(): void
    {
        $progression = $this->progressionWithUser();
        $this->prepRepo->method('count')
            ->willReturn(5);
        $this->spiceViewRepo->method('countDistinctPreparationMethodsSeenBy')
            ->willReturn(4);

        self::assertFalse($this->evaluator->isMet($this->makeAchievement(), $progression, []));
    }

    private function makeAchievement(): Achievement
    {
        return (new Achievement())
            ->setSlug('test-all-prep')
            ->setName('Test')
            ->setDescription('d')
            ->setTrigger(AchievementTrigger::ALL_PREPARATION_METHODS_READ)
            ->setTriggerValue(1)
            ->setXpReward(10)
            ->setRarity(AchievementRarity::LEGENDARY);
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
