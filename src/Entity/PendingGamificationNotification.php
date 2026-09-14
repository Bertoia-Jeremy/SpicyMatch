<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PendingGamificationNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PendingGamificationNotificationRepository::class)]
#[ORM\Table(name: 'pending_gamification_notification')]
#[ORM\Index(name: 'idx_pgn_user_delivered', columns: ['user_id', 'delivered_at'])]
class PendingGamificationNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Users $user;

    #[ORM\Column(length: 50)]
    private string $type;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(Users $user, string $type, array $payload)
    {
        $this->user = $user;
        $this->type = $type;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): Users
    {
        return $this->user;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function markDelivered(): static
    {
        $this->deliveredAt = new \DateTimeImmutable();

        return $this;
    }
}
