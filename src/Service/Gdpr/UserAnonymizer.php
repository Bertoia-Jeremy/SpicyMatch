<?php

declare(strict_types=1);

namespace App\Service\Gdpr;

use App\Entity\NewsletterSubscription;
use App\Entity\Users;
use App\Repository\NewsletterSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class UserAnonymizer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsletterSubscriptionRepository $newsletterRepository,
    ) {
    }

    public function anonymize(Users $user): void
    {
        foreach ($this->collectSubscriptions($user) as $subscription) {
            $this->entityManager->remove($subscription);
        }

        $user->setUsername('anonyme-'.$user->getId());
        $user->setMail(null);
        $user->setPassword('!'.bin2hex(random_bytes(32)));
        $user->setRoles([]);
        $user->setLastLoginAt(null);
    }

    /**
     * @return array<int, NewsletterSubscription>
     */
    private function collectSubscriptions(Users $user): array
    {
        $subscriptions = [];

        foreach ($this->newsletterRepository->findBy([
            'user' => $user,
        ]) as $subscription) {
            $subscriptions[$subscription->getId()] = $subscription;
        }

        $mail = $user->getMail();

        if (null !== $mail) {
            $byEmail = $this->newsletterRepository->findByEmail($mail);

            if (null !== $byEmail) {
                $subscriptions[$byEmail->getId()] = $byEmail;
            }
        }

        return $subscriptions;
    }
}
