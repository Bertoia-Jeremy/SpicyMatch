<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\NewsletterSubscription;
use PHPUnit\Framework\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    public function testIsActiveReturnsTrueWhenNotUnsubscribed(): void
    {
        $sub = new NewsletterSubscription();
        $sub->setEmail('test@example.com');

        self::assertTrue($sub->isActive);
    }

    public function testIsActiveReturnsFalseWhenUnsubscribed(): void
    {
        $sub = new NewsletterSubscription();
        $sub->setEmail('test@example.com');
        $sub->unsubscribe();

        self::assertFalse($sub->isActive);
    }

    public function testUnsubscribeSetsUnsubscribedAt(): void
    {
        $sub = new NewsletterSubscription();
        $sub->setEmail('test@example.com');

        self::assertNull($sub->getUnsubscribedAt());

        $sub->unsubscribe();

        self::assertInstanceOf(\DateTimeImmutable::class, $sub->getUnsubscribedAt());
    }

    public function testDefaultConsentSourceIsRegistration(): void
    {
        $sub = new NewsletterSubscription();
        $sub->setEmail('test@example.com');

        self::assertSame('registration', $sub->getConsentSource());
    }

    public function testSetConsentSource(): void
    {
        $sub = new NewsletterSubscription();
        $sub->setEmail('test@example.com');
        $sub->setConsentSource('footer');

        self::assertSame('footer', $sub->getConsentSource());
    }

    public function testSubscribedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $sub = new NewsletterSubscription();
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $sub->getSubscribedAt());
        self::assertLessThanOrEqual($after, $sub->getSubscribedAt());
    }
}
