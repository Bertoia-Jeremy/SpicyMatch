<?php

declare(strict_types=1);

namespace App\Tests\Gamification\Evaluator;

use App\Entity\Achievement;
use App\Entity\UserProgression;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Gamification\Evaluator\GameScoreThresholdEvaluator;
use PHPUnit\Framework\TestCase;

final class GameScoreThresholdEvaluatorTest extends TestCase
{
    private GameScoreThresholdEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new GameScoreThresholdEvaluator();
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

    public function testReturnsFalseWhenAchievementModeNull(): void
    {
        $achievement = $this->makeAchievement(null, 50);

        self::assertFalse($this->evaluator->isMet($achievement, new UserProgression(), [
            'gameMode' => 'chrono',
            'score' => 100,
        ]));
    }

    public function testReturnsFalseWhenModeDiffers(): void
    {
        $achievement = $this->makeAchievement(GameMode::CHRONO, 50);

        self::assertFalse($this->evaluator->isMet($achievement, new UserProgression(), [
            'gameMode' => 'qcm',
            'score' => 100,
        ]));
    }

    public function testReturnsFalseWhenDifficultyDiffers(): void
    {
        $achievement = $this->makeAchievement(GameMode::CHRONO, 50);
        $achievement->setContextDifficulty(GameDifficulty::HARD);

        self::assertFalse($this->evaluator->isMet($achievement, new UserProgression(), [
            'gameMode' => 'chrono',
            'difficulty' => 'easy',
            'score' => 100,
        ]));
    }

    public function testComparesContextScoreToThreshold(): void
    {
        $achievement = $this->makeAchievement(GameMode::CHRONO, 50);
        $progression = new UserProgression();

        self::assertTrue($this->evaluator->isMet($achievement, $progression, [
            'gameMode' => 'chrono',
            'score' => 55,
        ]));
        self::assertTrue($this->evaluator->isMet($achievement, $progression, [
            'gameMode' => 'chrono',
            'score' => 50,
        ]));
        self::assertFalse($this->evaluator->isMet($achievement, $progression, [
            'gameMode' => 'chrono',
            'score' => 49,
        ]));
    }

    private function makeAchievement(?GameMode $mode, int $triggerValue): Achievement
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

        return $a;
    }
}
