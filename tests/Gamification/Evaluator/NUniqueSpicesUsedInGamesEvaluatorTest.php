<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Gamification\Evaluator\NUniqueSpicesUsedInGamesEvaluator;
use App\Repository\GameSessionRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class NUniqueSpicesUsedInGamesEvaluatorTest extends TestCase
{
    private GameSessionRepository&MockObject $sessionRepo;
    private NUniqueSpicesUsedInGamesEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->sessionRepo = $this->createMock(GameSessionRepository::class);
        $this->evaluator = new NUniqueSpicesUsedInGamesEvaluator($this->sessionRepo);
    }

    public function testTriggerAndEventType(): void
    {
        self::assertSame(AchievementTrigger::N_UNIQUE_SPICES_USED_IN_GAMES, $this->evaluator->trigger());
        self::assertSame(['game_completed'], $this->evaluator->eventTypes());
    }

    public function testReturnsFalseWhenUserNull(): void
    {
        $progression = new UserProgression();
        self::assertFalse($this->evaluator->isMet($this->makeAchievement(10), $progression, []));
    }

    public function testRepoCountAboveThreshold(): void
    {
        $progression = $this->progressionWithUser();
        $this->sessionRepo->method('countDistinctTargetSpices')
            ->willReturn(10);

        self::assertTrue($this->evaluator->isMet($this->makeAchievement(10), $progression, []));
    }

    public function testRepoCountBelowThreshold(): void
    {
        $progression = $this->progressionWithUser();
        $this->sessionRepo->method('countDistinctTargetSpices')
            ->willReturn(9);

        self::assertFalse($this->evaluator->isMet($this->makeAchievement(10), $progression, []));
    }

    private function makeAchievement(int $triggerValue): Achievement
    {
        return (new Achievement())
            ->setSlug('test-unique-spices-games')
            ->setName('Test')
            ->setDescription('d')
            ->setTrigger(AchievementTrigger::N_UNIQUE_SPICES_USED_IN_GAMES)
            ->setTriggerValue($triggerValue)
            ->setXpReward(10)
            ->setRarity(AchievementRarity::RARE);
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
