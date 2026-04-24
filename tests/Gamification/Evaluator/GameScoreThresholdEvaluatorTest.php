<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\AromaticGroups;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Enum\GameMode;
use App\Gamification\Evaluator\GameScoreThresholdEvaluator;
use App\Repository\GameSessionRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class GameScoreThresholdEvaluatorTest extends TestCase
{
    private GameSessionRepository&MockObject $sessionRepo;
    private GameScoreThresholdEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->sessionRepo = $this->createMock(GameSessionRepository::class);
        $this->evaluator = new GameScoreThresholdEvaluator($this->sessionRepo);
    }

    public function testTriggerAndEventType(): void
    {
        self::assertSame(AchievementTrigger::GAME_SCORE_THRESHOLD, $this->evaluator->trigger());
        self::assertSame(['game_completed'], $this->evaluator->eventTypes());
    }

    public function testCurrentValueReadsFromContext(): void
    {
        $progression = new UserProgression();
        self::assertSame(42, $this->evaluator->currentValue($progression, [
            'score' => 42,
        ]));
        self::assertSame(0, $this->evaluator->currentValue($progression, []));
    }

    public function testReturnsFalseWhenUserNull(): void
    {
        $achievement = $this->makeAchievement(GameMode::CHRONO, null, 50);
        $progression = new UserProgression(); // no user

        self::assertFalse($this->evaluator->isMet($achievement, $progression, [
            'score' => 100,
        ]));
    }

    public function testReturnsFalseWhenAchievementModeNull(): void
    {
        $achievement = $this->makeAchievement(null, null, 50);
        $progression = $this->progressionWithUser();

        self::assertFalse($this->evaluator->isMet($achievement, $progression, [
            'score' => 100,
        ]));
    }

    public function testSessionScoreCaseUsesContextScore(): void
    {
        $achievement = $this->makeAchievement(GameMode::CHRONO, null, 50);
        $progression = $this->progressionWithUser();

        // Repository never queried in the no-group case.
        $this->sessionRepo->expects(self::never())->method('maxScoreInModeForGroup');

        self::assertTrue($this->evaluator->isMet($achievement, $progression, [
            'gameMode' => 'chrono',
            'score' => 55,
        ]));
        self::assertFalse($this->evaluator->isMet($achievement, $progression, [
            'gameMode' => 'chrono',
            'score' => 49,
        ]));
    }

    public function testGroupScopedCaseQueriesRepo(): void
    {
        $group = $this->createStub(AromaticGroups::class);
        $achievement = $this->makeAchievement(GameMode::CHRONO, $group, 100);
        $progression = $this->progressionWithUser();

        $this->sessionRepo->expects(self::once())
            ->method('maxScoreInModeForGroup')
            ->willReturn(120);

        self::assertTrue($this->evaluator->isMet($achievement, $progression, [
            'gameMode' => 'chrono',
        ]));
    }

    private function makeAchievement(?GameMode $mode, ?AromaticGroups $group, int $triggerValue): Achievement
    {
        $a = new Achievement();
        $a->setSlug('test-score')
            ->setName('Test')
            ->setDescription('d')
            ->setTrigger(AchievementTrigger::GAME_SCORE_THRESHOLD)
            ->setTriggerValue($triggerValue)
            ->setXpReward(10)
            ->setRarity(AchievementRarity::COMMON);
        if ($mode !== null) {
            $a->setContextGameMode($mode);
        }
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
