<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GdprRequestType;
use App\Repository\GdprRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GdprRequestRepository::class)]
#[ORM\Table(name: 'gdpr_request')]
class GdprRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(name: 'request_type', enumType: GdprRequestType::class)]
    private GdprRequestType $requestType = GdprRequestType::ERASURE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'treated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $treatedAt = null;

    public bool $isTreated {
        get => null !== $this->treatedAt;
    }

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getRequestType(): GdprRequestType
    {
        return $this->requestType;
    }

    public function setRequestType(GdprRequestType $requestType): static
    {
        $this->requestType = $requestType;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTreatedAt(): ?\DateTimeImmutable
    {
        return $this->treatedAt;
    }

    public function setTreatedAt(?\DateTimeImmutable $treatedAt): static
    {
        $this->treatedAt = $treatedAt;

        return $this;
    }

    public function markTreated(): static
    {
        $this->treatedAt = new \DateTimeImmutable();

        return $this;
    }
}
