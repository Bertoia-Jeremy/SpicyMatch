<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PendingGamificationNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stores gamification notifications (achievement_unlocked, level_up) pending delivery.
 * Consumed by GamificationNotificationSubscriber on the next HTML response.
 */
#[ORM\Entity(repositoryClass: PendingGamificationNotificationRepository::class)]
#[ORM\Table(name: 'pending_gamification_notification')]
class PendingGamificationNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Users $user;

    /**
     * 'achievement_unlocked' | 'level_up'.
     */
    #[ORM\Column(length: 50)]
    private string $type;

    /**
     * JSON payload: achievement slug, name, icon, rarity, xpReward or level.
     *
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
