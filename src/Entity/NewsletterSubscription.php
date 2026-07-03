<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NewsletterSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsletterSubscriptionRepository::class)]
#[ORM\UniqueConstraint(columns: ['email'])]
class NewsletterSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Users $user = null;

    #[ORM\Column]
    private \DateTimeImmutable $subscribedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    #[ORM\Column(length: 30, options: [
        'default' => 'registration',
    ])]
    private string $consentSource = 'registration';

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    public bool $isActive {
        get => null === $this->unsubscribedAt;
    }

    public function __construct()
    {
        $this->subscribedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function setUser(?Users $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSubscribedAt(): \DateTimeImmutable
    {
        return $this->subscribedAt;
    }

    public function getUnsubscribedAt(): ?\DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function getConsentSource(): string
    {
        return $this->consentSource;
    }

    public function setConsentSource(string $consentSource): static
    {
        $this->consentSource = $consentSource;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function unsubscribe(): static
    {
        $this->unsubscribedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
