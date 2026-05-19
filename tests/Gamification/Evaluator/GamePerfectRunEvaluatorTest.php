<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Enum\GameMode;
use App\Gamification\Evaluator\GamePerfectRunEvaluator;
use App\Repository\GameSessionRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class GamePerfectRunEvaluatorTest extends TestCase
{
    private GameSessionRepository&MockObject $sessionRepo;
    private GamePerfectRunEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->sessionRepo = $this->createMock(GameSessionRepository::class);
        $this->evaluator = new GamePerfectRunEvaluator($this->sessionRepo);
    }

    public function testTriggerAndEventType(): void
    {
        self::assertSame(AchievementTrigger::GAME_PERFECT_RUN, $this->evaluator->trigger());
        self::assertSame(['game_completed'], $this->evaluator->eventTypes());
    }

    public function testReturnsFalseWhenUserOrModeNull(): void
    {
        $noUser = new UserProgression();
        self::assertFalse($this->evaluator->isMet($this->makeAchievement(GameMode::INTRUS, 1), $noUser, []));

        self::assertFalse($this->evaluator->isMet($this->makeAchievement(null, 1), $this->progressionWithUser(), []));
    }

    public function testRepoCountComparedAgainstTriggerValue(): void
    {
        $progression = $this->progressionWithUser();
        $achievement = $this->makeAchievement(GameMode::INTRUS, 3);

        $this->sessionRepo->method('countPerfectRunsByMode')
            ->willReturn(3);
        // Achievement declares mode filter → ContextFilter rejects if context mode missing.
        self::assertTrue($this->evaluator->isMet($achievement, $progression, [
            'gameMode' => GameMode::INTRUS,
        ]));
    }

    public function testRepoCountBelowThresholdReturnsFalse(): void
    {
        $progression = $this->progressionWithUser();
        $achievement = $this->makeAchievement(GameMode::INTRUS, 5);

        $this->sessionRepo->method('countPerfectRunsByMode')
            ->willReturn(2);
        self::assertFalse($this->evaluator->isMet($achievement, $progression, [
            'gameMode' => GameMode::INTRUS,
        ]));
    }

    private function makeAchievement(?GameMode $mode, int $triggerValue): Achievement
    {
        $a = new Achievement();
        $a->setSlug('test-perfect')
            ->setName('Test')
            ->setDescription('d')
            ->setTrigger(AchievementTrigger::GAME_PERFECT_RUN)
            ->setTriggerValue($triggerValue)
            ->setXpReward(10)
            ->setRarity(AchievementRarity::EPIC);
        if ($mode !== null) {
            $a->setContextGameMode($mode);
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
