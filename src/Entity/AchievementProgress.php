<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AchievementProgressRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AchievementProgressRepository::class)]
#[ORM\UniqueConstraint(fields: ['user', 'achievement'])]
class AchievementProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'achievementProgress')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Users $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Achievement $achievement = null;

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $progress = 0;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /**
     * Virtual property using PHP 8.4 Property Hooks.
     */
    public bool $isCompleted {
        get => $this->progress >= ($this->achievement?->getTriggerValue() ?? 1);
    }

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAchievement(): ?Achievement
    {
        return $this->achievement;
    }

    public function setAchievement(?Achievement $achievement): static
    {
        $this->achievement = $achievement;

        return $this;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): static
    {
        $this->progress = $progress;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function incrementProgress(int $amount = 1): static
    {
        $this->progress += $amount;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
