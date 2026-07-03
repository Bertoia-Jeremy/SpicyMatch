<?php

declare(strict_types=1);

namespace App\Tests\Service\Gdpr;

use App\Entity\NewsletterSubscription;
use App\Entity\Users;
use App\Repository\NewsletterSubscriptionRepository;
use App\Service\Gdpr\UserAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class UserAnonymizerTest extends TestCase
{
    public function testAnonymizeScrubsPersonalData(): void
    {
        $user = $this->makeUser(42, 'chef-cumin', 'chef@example.com');

        $newsletterRepository = $this->createStub(NewsletterSubscriptionRepository::class);
        $newsletterRepository->method('findBy')
            ->willReturn([]);
        $newsletterRepository->method('findByEmail')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())
            ->method('remove');

        new UserAnonymizer($entityManager, $newsletterRepository)
            ->anonymize($user);

        $this->assertSame('anonyme-42', $user->getUserIdentifier());
        $this->assertNull($user->getMail());
        $this->assertStringStartsWith('!', (string) $user->getPassword());
        $this->assertSame(['ROLE_USER'], $user->getRoles());
        $this->assertNull($user->getLastLoginAt());
    }

    public function testAnonymizeRemovesNewsletterSubscriptions(): void
    {
        $user = $this->makeUser(7, 'chef-paprika', 'paprika@example.com');

        $subscriptionByUser = $this->makeSubscription(1, 'autre@example.com');
        $subscriptionByEmail = $this->makeSubscription(2, 'paprika@example.com');

        $newsletterRepository = $this->createStub(NewsletterSubscriptionRepository::class);
        $newsletterRepository->method('findBy')
            ->willReturn([$subscriptionByUser]);
        $newsletterRepository->method('findByEmail')
            ->willReturn($subscriptionByEmail);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))
            ->method('remove');

        new UserAnonymizer($entityManager, $newsletterRepository)
            ->anonymize($user);
    }

    public function testAnonymizeDeduplicatesSubscriptionFoundTwice(): void
    {
        $user = $this->makeUser(9, 'chef-safran', 'safran@example.com');

        $subscription = $this->makeSubscription(3, 'safran@example.com');

        $newsletterRepository = $this->createStub(NewsletterSubscriptionRepository::class);
        $newsletterRepository->method('findBy')
            ->willReturn([$subscription]);
        $newsletterRepository->method('findByEmail')
            ->willReturn($subscription);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('remove');

        new UserAnonymizer($entityManager, $newsletterRepository)
            ->anonymize($user);
    }

    private function makeUser(int $id, string $username, string $mail): Users
    {
        $user = new Users();
        new \ReflectionProperty(Users::class, 'id')->setValue($user, $id);
        $user->setUsername($username);
        $user->setMail($mail);
        $user->setPassword('hashed-password');
        $user->setLastLoginAt(new \DateTimeImmutable());

        return $user;
    }

    private function makeSubscription(int $id, string $email): NewsletterSubscription
    {
        $subscription = new NewsletterSubscription();
        new \ReflectionProperty(NewsletterSubscription::class, 'id')->setValue($subscription, $id);
        $subscription->setEmail($email);

        return $subscription;
    }
}
