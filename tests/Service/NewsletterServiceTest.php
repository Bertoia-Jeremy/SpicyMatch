<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\NewsletterSubscription;
use App\Entity\Users;
use App\Repository\NewsletterSubscriptionRepository;
use App\Service\NewsletterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class NewsletterServiceTest extends TestCase
{
    private const string APP_SECRET = 'test-secret-key-for-hmac';

    private EntityManagerInterface $em;

    private NewsletterSubscriptionRepository $repo;

    private NewsletterService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(NewsletterSubscriptionRepository::class);
        $this->service = new NewsletterService($this->em, $this->repo, self::APP_SECRET);
    }

    public function testSubscribeCreatesNewSubscription(): void
    {
        $this->repo->expects(self::once())
            ->method('findByEmail')
            ->with('new@example.com')
            ->willReturn(null);

        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $result = $this->service->subscribe('new@example.com', 'footer');

        self::assertSame('new@example.com', $result->getEmail());
        self::assertSame('footer', $result->getConsentSource());
    }

    public function testSubscribeReturnsExistingForDuplicateEmail(): void
    {
        $existing = new NewsletterSubscription();
        $existing->setEmail('dup@example.com');

        $this->repo->expects(self::once())
            ->method('findByEmail')
            ->with('dup@example.com')
            ->willReturn($existing);

        $this->em->expects(self::never())->method('persist');

        $result = $this->service->subscribe('dup@example.com');

        self::assertSame($existing, $result);
    }

    public function testSubscribeSetsConsentSource(): void
    {
        $this->repo->method('findByEmail')
            ->willReturn(null);

        $result = $this->service->subscribe('x@y.com', 'profile');

        self::assertSame('profile', $result->getConsentSource());
    }

    public function testSubscribeLinksUserWhenProvided(): void
    {
        $this->repo->method('findByEmail')
            ->willReturn(null);
        $user = new Users();

        $result = $this->service->subscribe('x@y.com', 'registration', $user);

        self::assertSame($user, $result->getUser());
    }

    public function testSubscribeSetsIpAddress(): void
    {
        $this->repo->method('findByEmail')
            ->willReturn(null);

        $result = $this->service->subscribe('x@y.com', 'footer', null, '192.168.1.1');

        self::assertSame('192.168.1.1', $result->getIpAddress());
    }

    public function testUnsubscribeSetsUnsubscribedAt(): void
    {
        $sub = new NewsletterSubscription();
        $sub->setEmail('active@example.com');

        $this->repo->expects(self::once())
            ->method('findActiveByEmail')
            ->with('active@example.com')
            ->willReturn($sub);

        $this->em->expects(self::once())->method('flush');

        $this->service->unsubscribe('active@example.com');

        self::assertFalse($sub->isActive);
    }

    public function testUnsubscribeIgnoresUnknownEmail(): void
    {
        $this->repo->expects(self::once())
            ->method('findActiveByEmail')
            ->with('unknown@example.com')
            ->willReturn(null);

        $this->em->expects(self::never())->method('flush');

        $this->service->unsubscribe('unknown@example.com');
    }

    public function testGenerateTokenIsConsistentForSameEmail(): void
    {
        $token1 = $this->service->generateUnsubscribeToken('test@example.com');
        $token2 = $this->service->generateUnsubscribeToken('test@example.com');

        self::assertSame($token1, $token2);
    }

    public function testGenerateTokenDiffersForDifferentEmails(): void
    {
        $tokenA = $this->service->generateUnsubscribeToken('a@example.com');
        $tokenB = $this->service->generateUnsubscribeToken('b@example.com');

        self::assertNotSame($tokenA, $tokenB);
    }

    public function testValidateTokenReturnsTrueForValidToken(): void
    {
        $token = $this->service->generateUnsubscribeToken('test@example.com');

        self::assertTrue($this->service->validateUnsubscribeToken('test@example.com', $token));
    }

    public function testValidateTokenReturnsFalseForTamperedToken(): void
    {
        $token = $this->service->generateUnsubscribeToken('test@example.com');

        self::assertFalse($this->service->validateUnsubscribeToken('test@example.com', $token . 'tampered'));
    }

    public function testValidateTokenReturnsFalseForWrongEmail(): void
    {
        $token = $this->service->generateUnsubscribeToken('a@example.com');

        self::assertFalse($this->service->validateUnsubscribeToken('b@example.com', $token));
    }
}
