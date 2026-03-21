<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CookieConsent;
use PHPUnit\Framework\TestCase;

class CookieConsentTest extends TestCase
{
    public function testDefaultAnalyticsConsentIsFalse(): void
    {
        $consent = new CookieConsent();

        self::assertFalse($consent->isAnalyticsConsent());
    }

    public function testDefaultFunctionalConsentIsFalse(): void
    {
        $consent = new CookieConsent();

        self::assertFalse($consent->isFunctionalConsent());
    }

    public function testDefaultConsentVersionIsOne(): void
    {
        $consent = new CookieConsent();

        self::assertSame(1, $consent->getConsentVersion());
    }

    public function testSetAnalyticsConsent(): void
    {
        $consent = new CookieConsent();
        $consent->setAnalyticsConsent(true);

        self::assertTrue($consent->isAnalyticsConsent());
    }

    public function testSetFunctionalConsent(): void
    {
        $consent = new CookieConsent();
        $consent->setFunctionalConsent(true);

        self::assertTrue($consent->isFunctionalConsent());
    }

    public function testConsentedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $consent = new CookieConsent();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $consent->getConsentedAt());
        self::assertLessThanOrEqual($after, $consent->getConsentedAt());
    }

    public function testSetSessionId(): void
    {
        $consent = new CookieConsent();
        $consent->setSessionId('abc123');

        self::assertSame('abc123', $consent->getSessionId());
    }

    public function testSetUserAgent(): void
    {
        $consent = new CookieConsent();
        $consent->setUserAgent('Mozilla/5.0');

        self::assertSame('Mozilla/5.0', $consent->getUserAgent());
    }
}
