<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Entity\UserProgression;
use App\Entity\Users;
use App\Enum\GameMode;
use App\Service\Clock\GameDay;
use App\Service\Education\DailyChallengeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class DailyChallengeResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: int, 2: list<GameMode>}>
     */
    public static function unlockedPoolProvider(): iterable
    {
        yield 'level 1' => ['2026-09-30 12:00:00', 1, [GameMode::QCM, GameMode::HANGMAN]];
        yield 'level 3' => ['2026-09-30 12:00:00', 3, [GameMode::QCM, GameMode::GUESS_WHO, GameMode::INTRUS, GameMode::HANGMAN]];
        yield 'level 10' => ['2026-09-30 12:00:00', 10, GameMode::cases()];
    }

    /**
     * @param list<GameMode> $pool
     */
    #[DataProvider('unlockedPoolProvider')]
    public function testPicksOnlyUnlockedModesByDayOrdinal(string $now, int $level, array $pool): void
    {
        $resolver = $this->resolver($now);
        $day = new GameDay(new MockClock($now), 'Europe/Paris')
            ->today();

        self::assertSame(
            $pool[GameDay::ordinal($day) % \count($pool)],
            $resolver->forUser($this->userAtLevel($level)),
        );
    }

    public function testAnonymousUsesLevelOnePool(): void
    {
        $resolver = $this->resolver('2026-09-30 12:00:00');

        self::assertSame($resolver->forUser($this->userAtLevel(1)), $resolver->forUser(null));
    }

    public function testGamificationDisabledReturnsNull(): void
    {
        $progression = new UserProgression();
        $progression->disableGamification();
        $user = $this->createStub(Users::class);
        $user->method('getProgression')
            ->willReturn($progression);

        self::assertNull($this->resolver('2026-09-30 12:00:00')->forUser($user));
    }

    public function testRotatesAtParisMidnightNotUtc(): void
    {
        $user = $this->userAtLevel(10);

        $beforeMidnightParis = $this->resolver('2026-09-30 21:59:00', 'UTC')
            ->forUser($user);
        $afterMidnightParis = $this->resolver('2026-09-30 22:01:00', 'UTC')
            ->forUser($user);

        self::assertNotSame($beforeMidnightParis, $afterMidnightParis);
        self::assertSame($afterMidnightParis, $this->resolver('2026-10-01 12:00:00')->forUser($user));
    }

    public function testTomorrowDiffersFromToday(): void
    {
        $resolver = $this->resolver('2026-09-30 12:00:00');
        $user = $this->userAtLevel(10);

        self::assertNotSame($resolver->forUser($user), $resolver->forUserTomorrow($user));
        self::assertSame($this->resolver('2026-10-01 08:00:00')->forUser($user), $resolver->forUserTomorrow($user));
    }

    private function resolver(string $now, string $clockTimezone = 'Europe/Paris'): DailyChallengeResolver
    {
        return new DailyChallengeResolver(new GameDay(new MockClock($now, $clockTimezone), 'Europe/Paris'));
    }

    private function userAtLevel(int $level): Users
    {
        $progression = new UserProgression();
        $progression->addXp((int) ceil(100 * ($level ** 1.3)) + 5);
        $user = $this->createStub(Users::class);
        $user->method('getProgression')
            ->willReturn($progression);

        return $user;
    }
}
