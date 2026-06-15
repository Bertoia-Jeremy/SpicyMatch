<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Users;
use PHPUnit\Framework\TestCase;

final class UsersPremiumTest extends TestCase
{
    public function testPremiumUntilDefaultsToNull(): void
    {
        self::assertNull(new Users()->getPremiumUntil());
    }

    public function testIsPremiumFalseWhenNeverSubscribed(): void
    {
        self::assertFalse(new Users()->isPremium());
    }

    public function testIsPremiumTrueWhenDateInFuture(): void
    {
        $user = new Users();
        $user->setPremiumUntil(new \DateTimeImmutable('+1 month'));

        self::assertTrue($user->isPremium());
    }

    public function testIsPremiumFalseWhenDateInPast(): void
    {
        $user = new Users();
        $user->setPremiumUntil(new \DateTimeImmutable('-1 day'));

        self::assertFalse($user->isPremium());
    }

    public function testIsPremiumFalseAtExactExpiry(): void
    {
        $now = new \DateTimeImmutable('2026-06-13 12:00:00');
        $user = new Users();
        $user->setPremiumUntil($now);

        self::assertFalse($user->isPremium($now));
    }

    public function testIsPremiumUsesProvidedClock(): void
    {
        $user = new Users();
        $user->setPremiumUntil(new \DateTimeImmutable('2026-12-31 23:59:59'));

        self::assertTrue($user->isPremium(new \DateTimeImmutable('2026-06-13')));
        self::assertFalse($user->isPremium(new \DateTimeImmutable('2027-01-01')));
    }

    public function testSetPremiumUntilIsFluent(): void
    {
        $user = new Users();

        self::assertSame($user, $user->setPremiumUntil(null));
    }
}
