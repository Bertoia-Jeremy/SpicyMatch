<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProcessedGamificationEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProcessedGamificationEventRepository::class)]
#[ORM\Table(name: 'processed_gamification_event')]
#[ORM\UniqueConstraint(name: 'uniq_processed_event', columns: ['event_type', 'event_key'])]
#[ORM\Index(name: 'idx_processed_event_user', columns: ['user_id'])]
class ProcessedGamificationEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $eventType;

    #[ORM\Column(length: 191)]
    private string $eventKey;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Users $user;

    #[ORM\Column]
    private \DateTimeImmutable $processedAt;

    public function __construct(Users $user, string $eventType, string $eventKey)
    {
        $this->user = $user;
        $this->eventType = $eventType;
        $this->eventKey = $eventKey;
        $this->processedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getEventKey(): string
    {
        return $this->eventKey;
    }

    public function getUser(): Users
    {
        return $this->user;
    }
}
