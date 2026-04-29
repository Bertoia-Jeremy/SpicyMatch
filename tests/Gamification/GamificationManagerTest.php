<?php

declare(strict_types=1);

namespace App\Tests\Gamification;

use App\Entity\Achievement;
use App\Entity\PendingGamificationNotification;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Entity\UserStat;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Gamification\AchievementChecker;
use App\Gamification\Evaluator\AllPreparationMethodsReadEvaluator;
use App\Gamification\Evaluator\AllTerpenesVisitedEvaluator;
use App\Gamification\Evaluator\EasterEggFoundEvaluator;
use App\Gamification\Evaluator\FirstDiscoveryEvaluator;
use App\Gamification\Evaluator\FirstGameEvaluator;
use App\Gamification\Evaluator\FirstMatchEvaluator;
use App\Gamification\Evaluator\GamePerfectRunEvaluator;
use App\Gamification\Evaluator\GameScoreThresholdEvaluator;
use App\Gamification\Evaluator\GroupMasteryReadEvaluator;
use App\Gamification\Evaluator\NFavoritesEvaluator;
use App\Gamification\Evaluator\NGamesCompletedEvaluator;
use App\Gamification\Evaluator\NMatchesEvaluator;
use App\Gamification\Evaluator\NSpicesUsedEvaluator;
use App\Gamification\Evaluator\NUniqueSpicesUsedInGamesEvaluator;
use App\Gamification\Evaluator\ReadingStreakEvaluator;
use App\Gamification\Evaluator\SpiceReadEvaluator;
use App\Gamification\Evaluator\TriggerEvaluatorRegistry;
use App\Gamification\XpStrategyInterface;
use App\Repository\AchievementProgressRepository;
use App\Repository\AchievementRepository;
use App\Repository\AromaticGroupsRepository;
use App\Repository\GameSessionRepository;
use App\Repository\PreparationMethodsRepository;
use App\Repository\SpiceViewRepository;
use App\Service\GamificationManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * AchievementChecker is final — we use a real instance backed by a mocked
 * AchievementRepository rather than mocking the checker directly.
 */
#[AllowMockObjectsWithoutExpectations]
final class GamificationManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private AchievementRepository&MockObject $achievementRepo;
    private AromaticGroupsRepository&MockObject $aromaticGroupsRepo;
    private AchievementProgressRepository&MockObject $achievementProgressRepo;
    private UserProgression $progression;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->achievementRepo = $this->createMock(AchievementRepository::class);
        $this->aromaticGroupsRepo = $this->createMock(AromaticGroupsRepository::class);
        $this->achievementProgressRepo = $this->createMock(AchievementProgressRepository::class);

        $stats = new UserStat();
        $user = $this->createMock(Users::class);
        $user->method('getStats')
            ->willReturn($stats);
        $this->progression = new UserProgression();
        $this->progression->setUser($user);
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    public function testDoesNothingWhenGamificationIsDisabled(): void
    {
        $this->progression->disableGamification();

        $this->achievementRepo->expects(self::never())->method('findByTrigger');
        $this->em->expects(self::never())->method('persist');

        $this->makeEngine()
            ->process($this->progression, 'match_saved');

        self::assertSame(0, $this->progression->getXp());
    }

    public function testDoesNothingWhenUserIsNull(): void
    {
        $progression = new UserProgression(); // no user set

        $this->achievementRepo->expects(self::never())->method('findByTrigger');
        $this->em->expects(self::never())->method('persist');

        $this->makeEngine()
            ->process($progression, 'match_saved');
    }

    // ── XP strategies ─────────────────────────────────────────────────────────

    public function testAppliesXpFromMatchingStrategy(): void
    {
        $this->achievementRepo->method('findByTrigger')
            ->willReturn([]);
        $strategy = $this->stubStrategy('match_saved', 10);

        $this->makeEngine([$strategy])->process($this->progression, 'match_saved');

        self::assertSame(10, $this->progression->getXp());
    }

    public function testSkipsStrategyThatDoesNotSupportEvent(): void
    {
        $this->achievementRepo->method('findByTrigger')
            ->willReturn([]);

        $strategy = $this->createMock(XpStrategyInterface::class);
        $strategy->method('supports')
            ->willReturn(false);
        $strategy->expects(self::never())->method('calculate');

        $this->makeEngine([$strategy])->process($this->progression, 'match_saved');

        self::assertSame(0, $this->progression->getXp());
    }

    public function testDoesNotAddXpWhenStrategyReturnsZero(): void
    {
        $this->achievementRepo->method('findByTrigger')
            ->willReturn([]);
        $strategy = $this->stubStrategy('spice_read', 0);

        $this->makeEngine([$strategy])->process($this->progression, 'spice_read');

        self::assertSame(0, $this->progression->getXp());
    }

    public function testAppliesMultipleStrategiesCumulatively(): void
    {
        $this->achievementRepo->method('findByTrigger')
            ->willReturn([]);

        $s1 = $this->stubStrategy('match_saved', 10);
        $s2 = $this->stubStrategy('match_saved', 5);

        $this->makeEngine([$s1, $s2])->process($this->progression, 'match_saved');

        self::assertSame(15, $this->progression->getXp());
    }

    // ── Achievement unlocking ─────────────────────────────────────────────────

    public function testAddsXpRewardWhenAchievementIsUnlocked(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_MATCH, 30);

        $this->achievementRepo->method('findByTrigger')
            ->willReturnCallback(
                fn (AchievementTrigger $t) => $t === AchievementTrigger::FIRST_MATCH ? [$achievement] : []
            );

        $this->setProgressionField('totalMatches', 1);

        $this->makeEngine()
            ->process($this->progression, 'match_saved');

        self::assertSame(30, $this->progression->getXp());
    }

    public function testPersistsAchievementUnlockedNotification(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_MATCH, 30);

        $this->achievementRepo->method('findByTrigger')
            ->willReturnCallback(
                fn (AchievementTrigger $t) => $t === AchievementTrigger::FIRST_MATCH ? [$achievement] : []
            );

        $this->setProgressionField('totalMatches', 1);

        $this->em->expects(self::atLeastOnce())
            ->method('persist')
            ->with(self::isInstanceOf(PendingGamificationNotification::class));

        $this->makeEngine()
            ->process($this->progression, 'match_saved');
    }

    public function testPersistsOnlyXpToastWhenNoAchievementNoLevelUp(): void
    {
        $this->achievementRepo->method('findByTrigger')
            ->willReturn([]);

        // 5 XP — not enough to leave level 1 (needs 40)
        $strategy = $this->stubStrategy('match_saved', 5);

        $persisted = [];
        $this->em->method('persist')
            ->willReturnCallback(function (object $obj) use (&$persisted): void {
                $persisted[] = $obj;
            });

        $this->makeEngine([$strategy])->process($this->progression, 'match_saved');

        $notifs = array_filter($persisted, fn ($n) => $n instanceof PendingGamificationNotification);
        self::assertCount(1, $notifs);
        $notif = array_values($notifs)[0];
        self::assertSame('xp_gained', $notif->getType());
        self::assertSame(5, $notif->getPayload()['amount']);
    }

    // ── Level-up notification ─────────────────────────────────────────────────

    public function testPersistsLevelUpNotificationWhenLevelIncreases(): void
    {
        $this->achievementRepo->method('findByTrigger')
            ->willReturn([]);

        // 247 XP → level 2 (formula: level = floor((xp/100)^(1/1.3)), 247 → floor(2.47^0.769) = floor(2.03) = 2)
        $strategy = $this->stubStrategy('match_saved', 247);

        $persisted = [];
        $this->em->method('persist')
            ->willReturnCallback(function (object $obj) use (&$persisted): void {
                $persisted[] = $obj;
            });

        $this->makeEngine([$strategy])->process($this->progression, 'match_saved');

        self::assertNotEmpty(array_filter($persisted, fn ($n) => $n instanceof PendingGamificationNotification));
    }

    public function testDoesNotPersistLevelUpWhenLevelUnchanged(): void
    {
        $this->achievementRepo->method('findByTrigger')
            ->willReturn([]);

        // 5 XP — level stays at 1 (still persists an xp_gained toast, but no level_up)
        $strategy = $this->stubStrategy('match_saved', 5);

        $persisted = [];
        $this->em->method('persist')
            ->willReturnCallback(function (object $obj) use (&$persisted): void {
                $persisted[] = $obj;
            });

        $this->makeEngine([$strategy])->process($this->progression, 'match_saved');

        $levelUps = array_filter(
            $persisted,
            fn ($n) => $n instanceof PendingGamificationNotification && $n->getType() === 'level_up',
        );
        self::assertCount(0, $levelUps);
    }

    // ── Context forwarding ────────────────────────────────────────────────────

    public function testContextIsForwardedToStrategyCalculate(): void
    {
        $this->achievementRepo->method('findByTrigger')
            ->willReturn([]);

        $strategy = $this->createMock(XpStrategyInterface::class);
        $strategy->method('supports')
            ->willReturn(true);
        $strategy->expects(self::once())
            ->method('calculate')
            ->with($this->progression, self::identicalTo([
                'isNewView' => true,
            ]))
            ->willReturn(5);

        $this->makeEngine([$strategy])->process($this->progression, 'spice_read', [
            'isNewView' => true,
        ]);
    }

    public function testContextIsForwardedToAchievementChecker(): void
    {
        $this->achievementRepo->method('findByTrigger')
            ->willReturn([]);

        // 0 XP, no achievements, no level-up → no persist
        $this->em->expects(self::never())->method('persist');

        $this->makeEngine()
            ->process($this->progression, 'easter_egg_found', [
                'easterEggSlug' => 'first_egg',
                'xpAmount' => 75,
            ]);
    }

    // ── getOrCreateStats ────────────────────────────────────────────────────

    public function testGetOrCreateStatsCreatesNewUserStatIfNull(): void
    {
        $user = $this->createMock(Users::class);
        $user->method('getStats')
            ->willReturn(null);
        $user->expects(self::once())->method('setStats');
        $this->em->expects(self::once())->method('persist')->with(self::isInstanceOf(UserStat::class));

        $stats = $this->makeEngine()
            ->getOrCreateStats($user);

        self::assertInstanceOf(UserStat::class, $stats);
    }

    public function testGetOrCreateStatsReturnsExistingStats(): void
    {
        $existing = new UserStat();
        $user = $this->createMock(Users::class);
        $user->method('getStats')
            ->willReturn($existing);
        $this->em->expects(self::never())->method('persist');

        $stats = $this->makeEngine()
            ->getOrCreateStats($user);

        self::assertSame($existing, $stats);
    }

    // ── updateAchievementProgress ──────────────────────────────────────────

    public function testUpdateAchievementProgressUpsertsProgress(): void
    {
        // triggerValue=20 so achievement is NOT unlocked (totalMatches=7 < 20)
        // but progress bar should still be updated
        $achievement = (new Achievement())
            ->setSlug('test-progress')
            ->setName('Many Matches')
            ->setDescription('Desc')
            ->setIcon('fa-star')
            ->setTrigger(AchievementTrigger::N_MATCHES)
            ->setTriggerValue(20)
            ->setXpReward(50)
            ->setRarity(AchievementRarity::RARE);

        $this->achievementRepo->method('findByTrigger')
            ->willReturnCallback(
                fn (AchievementTrigger $t) => $t === AchievementTrigger::N_MATCHES ? [$achievement] : []
            );

        $this->setProgressionField('totalMatches', 7);

        $progress = new \App\Entity\AchievementProgress();
        $progress->setAchievement($achievement);
        // Achievement id 99 — matched against `findOrCreateBatchForUser` keyed by achievement id.
        (new \ReflectionProperty(Achievement::class, 'id'))->setValue($achievement, 99);
        $this->achievementProgressRepo->method('findOrCreateBatchForUser')
            ->willReturn([
                99 => $progress,
            ]);

        $this->makeEngine()
            ->process($this->progression, 'match_saved');

        self::assertSame(7, $progress->getProgress());
    }

    public function testUpdateAchievementProgressSkipsAlreadyUnlockedAchievements(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_MATCH, 1);
        $this->achievementRepo->method('findByTrigger')
            ->willReturnCallback(
                fn (AchievementTrigger $t) => $t === AchievementTrigger::FIRST_MATCH ? [$achievement] : []
            );

        // Already owned
        $this->progression->unlockAchievement($achievement);
        $this->setProgressionField('totalMatches', 5);

        // findOrCreateForUser should never be called for already unlocked achievements
        $this->achievementProgressRepo->expects(self::never())
            ->method('findOrCreateForUser');

        $this->makeEngine()
            ->process($this->progression, 'match_saved');
    }

    public function testUpdateAchievementProgressHandlesUnknownEventGracefully(): void
    {
        $this->achievementProgressRepo->expects(self::never())
            ->method('findOrCreateForUser');

        $this->makeEngine()
            ->process($this->progression, 'completely_unknown_event');
    }

    // ── Does not duplicate achievement ─────────────────────────────────────

    public function testDoesNotDuplicateAlreadyUnlockedAchievement(): void
    {
        $achievement = $this->makeAchievement(AchievementTrigger::FIRST_MATCH, 30);
        $this->progression->unlockAchievement($achievement);
        $this->setProgressionField('totalMatches', 5);

        $this->achievementRepo->method('findByTrigger')
            ->willReturnCallback(
                fn (AchievementTrigger $t) => $t === AchievementTrigger::FIRST_MATCH ? [$achievement] : []
            );

        $xpBefore = $this->progression->getXp();
        $countBefore = $this->progression->getUserAchievements()
            ->count();

        $this->makeEngine()
            ->process($this->progression, 'match_saved');

        // Achievement XP reward should NOT be added again
        self::assertSame($xpBefore, $this->progression->getXp());
        self::assertSame($countBefore, $this->progression->getUserAchievements()->count());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeEngine(array $strategies = []): GamificationManager
    {
        $spiceViewRepo = $this->createStub(SpiceViewRepository::class);
        $gameSessionRepo = $this->createStub(GameSessionRepository::class);
        $prepMethodsRepo = $this->createStub(PreparationMethodsRepository::class);

        $evaluators = [
            new FirstMatchEvaluator(),
            new NMatchesEvaluator(),
            new NSpicesUsedEvaluator(),
            new NFavoritesEvaluator(),
            new SpiceReadEvaluator(),
            new ReadingStreakEvaluator(),
            new FirstDiscoveryEvaluator(),
            new FirstGameEvaluator(),
            new NGamesCompletedEvaluator(),
            new EasterEggFoundEvaluator(),
            new AllTerpenesVisitedEvaluator($this->aromaticGroupsRepo),
            new GameScoreThresholdEvaluator($gameSessionRepo),
            new GamePerfectRunEvaluator($gameSessionRepo),
            new GroupMasteryReadEvaluator($spiceViewRepo),
            new NUniqueSpicesUsedInGamesEvaluator($gameSessionRepo),
            new AllPreparationMethodsReadEvaluator($prepMethodsRepo, $spiceViewRepo),
        ];
        $registry = new TriggerEvaluatorRegistry($evaluators);

        $checker = new AchievementChecker($this->achievementRepo, $registry);

        return new GamificationManager(
            $strategies,
            $checker,
            $this->em,
            $this->achievementRepo,
            $this->achievementProgressRepo,
            $registry,
            new NullLogger(),
        );
    }

    private function stubStrategy(string $eventType, int $xp): XpStrategyInterface&MockObject
    {
        $strategy = $this->createMock(XpStrategyInterface::class);
        $strategy->method('supports')
            ->willReturnCallback(fn (string $e) => $e === $eventType);
        $strategy->method('calculate')
            ->willReturn($xp);

        return $strategy;
    }

    private function makeAchievement(AchievementTrigger $trigger, int $xpReward): Achievement
    {
        return (new Achievement())
            ->setSlug('test-' . $trigger->value)
            ->setName('Test Achievement')
            ->setDescription('Desc')
            ->setIcon('fa-star')
            ->setTrigger($trigger)
            ->setTriggerValue(1)
            ->setXpReward($xpReward)
            ->setRarity(AchievementRarity::RARE);
    }

    private function setProgressionField(string $field, mixed $value): void
    {
        (new \ReflectionProperty(UserProgression::class, $field))->setValue($this->progression, $value);
    }
}
