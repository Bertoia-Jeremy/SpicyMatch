<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CookieConsentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CookieConsentRepository::class)]
class CookieConsent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $sessionId;

    #[ORM\Column(options: [
        'default' => false,
    ])]
    private bool $analyticsConsent = false;

    #[ORM\Column(options: [
        'default' => false,
    ])]
    private bool $functionalConsent = false;

    #[ORM\Column]
    private \DateTimeImmutable $consentedAt;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(options: [
        'default' => 1,
    ])]
    private int $consentVersion = 1;

    public function __construct()
    {
        $this->consentedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): static
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function isAnalyticsConsent(): bool
    {
        return $this->analyticsConsent;
    }

    public function setAnalyticsConsent(bool $analyticsConsent): static
    {
        $this->analyticsConsent = $analyticsConsent;

        return $this;
    }

    public function isFunctionalConsent(): bool
    {
        return $this->functionalConsent;
    }

    public function setFunctionalConsent(bool $functionalConsent): static
    {
        $this->functionalConsent = $functionalConsent;

        return $this;
    }

    public function getConsentedAt(): \DateTimeImmutable
    {
        return $this->consentedAt;
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

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getConsentVersion(): int
    {
        return $this->consentVersion;
    }

    public function setConsentVersion(int $consentVersion): static
    {
        $this->consentVersion = $consentVersion;

        return $this;
    }
}
