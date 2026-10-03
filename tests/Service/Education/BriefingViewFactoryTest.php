<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\GameMode;
use App\Repository\GameSessionRepository;
use App\Repository\SpicesRepository;
use App\Service\Education\AcademyManager;
use App\Service\Education\BriefingFacts;
use App\Service\Education\BriefingViewFactory;
use App\Service\Education\GameSessionManager;
use App\Service\Match\CompatibleSpiceFinder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Translation\IdentityTranslator;

final class BriefingViewFactoryTest extends TestCase
{
    public function testBuildReportsQuotaBonusAndRecord(): void
    {
        $view = $this->factory(played: 5, bonus: true, best: [
            'chrono' => 42,
        ])->build($this->user(100_000), GameMode::CHRONO);

        self::assertFalse($view->levelLocked);
        self::assertSame(5, $view->playedToday);
        self::assertSame(0, $view->remainingToday());
        self::assertTrue($view->dailyBonus);
        self::assertSame(42, $view->best);
        self::assertSame(GameSessionManager::MAX_DAILY_SESSIONS_PREMIUM, $view->premiumMaxDailySessions);
        self::assertSame(GameSessionManager::MAX_XP_PER_SESSION, $view->maxXpPerSession);
        self::assertNotEmpty($view->facts);
        self::assertSame(['ui.edu.rule.chrono_0', 'ui.edu.rule.chrono_1', 'ui.edu.rule.chrono_2'], $view->rules);
    }

    public function testBuildLocksModeAboveUserLevel(): void
    {
        $view = $this->factory(played: 0, bonus: false, best: [])->build($this->user(0), GameMode::CHRONO);

        self::assertTrue($view->levelLocked);
        self::assertNull($view->best);
        self::assertSame(GameSessionManager::MAX_DAILY_SESSIONS_FREE, $view->remainingToday());
    }

    /**
     * @param array<string, int> $best
     */
    private function factory(int $played, bool $bonus, array $best): BriefingViewFactory
    {
        $sessionManager = $this->createStub(GameSessionManager::class);
        $sessionManager->method('countTodaySessions')
            ->willReturn($played);
        $sessionManager->method('maxDailySessions')
            ->willReturn(GameSessionManager::MAX_DAILY_SESSIONS_FREE);
        $sessionManager->method('qualifiesForDailyBonus')
            ->willReturn($bonus);

        $repository = $this->createStub(GameSessionRepository::class);
        $repository->method('findBestScoreByUserGrouped')
            ->willReturn($best);

        $academyManager = new AcademyManager(
            $this->createStub(SpicesRepository::class),
            $this->createStub(CompatibleSpiceFinder::class),
            new ArrayAdapter(),
            new IdentityTranslator(),
        );

        return new BriefingViewFactory(new BriefingFacts($academyManager), $academyManager, $sessionManager, $repository);
    }

    private function user(int $xp): Users
    {
        $user = new Users();
        $progression = new UserProgression();
        $progression->addXp($xp);
        $user->setProgression($progression);

        return $user;
    }
}
