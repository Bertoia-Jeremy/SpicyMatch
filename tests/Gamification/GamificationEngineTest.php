<?php

declare(strict_types=1);

namespace App\Tests\Gamification;

use App\Entity\Achievement;
use App\Entity\PendingGamificationNotification;
use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Gamification\AchievementChecker;
use App\Gamification\GamificationEngine;
use App\Gamification\XpStrategyInterface;
use App\Repository\AchievementRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * AchievementChecker is final — we use a real instance backed by a mocked
 * AchievementRepository rather than mocking the checker directly.
 */
#[AllowMockObjectsWithoutExpectations]
final class GamificationEngineTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private AchievementRepository&MockObject $achievementRepo;
    private UserProgression $progression;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->achievementRepo = $this->createMock(AchievementRepository::class);

        $user = $this->createMock(Users::class);
        $this->progression = new UserProgression();
        $this->progression->setUser($user);
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    public function testDoesNothingWhenGamificationIsDisabled(): void
    {
        $this->progression->disableGamification();

        $this->achievementRepo->expects(self::never())->method('findByTrigger');
        $this->em->expects(self::never())->method('persist');

        $this->makeEngine()->process($this->progression, 'match_saved');

        self::assertSame(0, $this->progression->getXp());
    }

    public function testDoesNothingWhenUserIsNull(): void
    {
        $progression = new UserProgression(); // no user set

        $this->achievementRepo->expects(self::never())->method('findByTrigger');
        $this->em->expects(self::never())->method('persist');

        $this->makeEngine()->process($progression, 'match_saved');
    }

    // ── XP strategies ─────────────────────────────────────────────────────────

    public function testAppliesXpFromMatchingStrategy(): void
    {
        $this->achievementRepo->method('findByTrigger')->willReturn([]);
        $strategy = $this->stubStrategy('match_saved', 10);

        $this->makeEngine([$strategy])->process($this->progression, 'match_saved');

        self::assertSame(10, $this->progression->getXp());
    }

    public function testSkipsStrategyThatDoesNotSupportEvent(): void
    {
        $this->achievementRepo->method('findByTrigger')->willReturn([]);

        $strategy = $this->createMock(XpStrategyInterface::class);
        $strategy->method('supports')->willReturn(false);
        $strategy->expects(self::never())->method('calculate');

        $this->makeEngine([$strategy])->process($this->progression, 'match_saved');

        self::assertSame(0, $this->progression->getXp());
    }

    public function testDoesNotAddXpWhenStrategyReturnsZero(): void
    {
        $this->achievementRepo->method('findByTrigger')->willReturn([]);
        $strategy = $this->stubStrategy('spice_read', 0);

        $this->makeEngine([$strategy])->process($this->progression, 'spice_read');

        self::assertSame(0, $this->progression->getXp());
    }

    public function testAppliesMultipleStrategiesCumulatively(): void
    {
        $this->achievementRepo->method('findByTrigger')->willReturn([]);

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

        $this->makeEngine()->process($this->progression, 'match_saved');

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

        $this->makeEngine()->process($this->progression, 'match_saved');
    }

    public function testNoPersistWhenNoAchievementsUnlockedAndNoLevelUp(): void
    {
        $this->achievementRepo->method('findByTrigger')->willReturn([]);

        // 5 XP — not enough to leave level 1 (needs 40)
        $strategy = $this->stubStrategy('match_saved', 5);

        $this->em->expects(self::never())->method('persist');

        $this->makeEngine([$strategy])->process($this->progression, 'match_saved');
    }

    // ── Level-up notification ─────────────────────────────────────────────────

    public function testPersistsLevelUpNotificationWhenLevelIncreases(): void
    {
        $this->achievementRepo->method('findByTrigger')->willReturn([]);

        // 40 XP: level 1 → 3 (floor(sqrt(40/10)) + 1 = 3)
        $strategy = $this->stubStrategy('match_saved', 40);

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $obj) use (&$persisted): void {
            $persisted[] = $obj;
        });

        $this->makeEngine([$strategy])->process($this->progression, 'match_saved');

        self::assertNotEmpty(array_filter($persisted, fn ($n) => $n instanceof PendingGamificationNotification));
    }

    public function testDoesNotPersistLevelUpWhenLevelUnchanged(): void
    {
        $this->achievementRepo->method('findByTrigger')->willReturn([]);

        // 5 XP — level stays at 1
        $strategy = $this->stubStrategy('match_saved', 5);

        $this->em->expects(self::never())->method('persist');

        $this->makeEngine([$strategy])->process($this->progression, 'match_saved');
    }

    // ── Context forwarding ────────────────────────────────────────────────────

    public function testContextIsForwardedToStrategyCalculate(): void
    {
        $this->achievementRepo->method('findByTrigger')->willReturn([]);

        $strategy = $this->createMock(XpStrategyInterface::class);
        $strategy->method('supports')->willReturn(true);
        $strategy->expects(self::once())
            ->method('calculate')
            ->with($this->progression, self::identicalTo(['isNewView' => true]))
            ->willReturn(5);

        $this->makeEngine([$strategy])->process($this->progression, 'spice_read', ['isNewView' => true]);
    }

    public function testContextIsForwardedToAchievementChecker(): void
    {
        $this->achievementRepo->method('findByTrigger')->willReturn([]);

        // 0 XP, no achievements, no level-up → no persist
        $this->em->expects(self::never())->method('persist');

        $this->makeEngine()->process($this->progression, 'easter_egg_found', [
            'easterEggSlug' => 'first_egg',
            'xpAmount' => 75,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeEngine(array $strategies = []): GamificationEngine
    {
        return new GamificationEngine($strategies, new AchievementChecker($this->achievementRepo), $this->em);
    }

    private function stubStrategy(string $eventType, int $xp): XpStrategyInterface&MockObject
    {
        $strategy = $this->createMock(XpStrategyInterface::class);
        $strategy->method('supports')->willReturnCallback(fn (string $e) => $e === $eventType);
        $strategy->method('calculate')->willReturn($xp);

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
