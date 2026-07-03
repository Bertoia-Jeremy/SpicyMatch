<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NewsletterSubscription;
use App\Entity\Users;
use App\Repository\NewsletterSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

class NewsletterService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NewsletterSubscriptionRepository $subscriptionRepository,
        private readonly string $appSecret,
    ) {
    }

    public function subscribe(
        string $email,
        string $consentSource = 'registration',
        ?Users $user = null,
        ?string $ip = null,
    ): NewsletterSubscription {
        $existing = $this->subscriptionRepository->findByEmail($email);
        if (null !== $existing) {
            return $existing;
        }

        $subscription = new NewsletterSubscription();
        $subscription->setEmail($email);
        $subscription->setConsentSource($consentSource);
        $subscription->setUser($user);
        $subscription->setIpAddress($ip);

        $this->em->persist($subscription);
        $this->em->flush();

        return $subscription;
    }

    public function unsubscribe(string $email): void
    {
        $subscription = $this->subscriptionRepository->findActiveByEmail($email);
        if (null === $subscription) {
            return;
        }

        $subscription->unsubscribe();
        $this->em->flush();
    }

    public function generateUnsubscribeToken(string $email): string
    {
        return hash_hmac('sha256', $email, $this->appSecret);
    }

    public function validateUnsubscribeToken(string $email, string $token): bool
    {
        return hash_equals($this->generateUnsubscribeToken($email), $token);
    }
}
