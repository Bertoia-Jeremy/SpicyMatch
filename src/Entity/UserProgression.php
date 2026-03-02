<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserProgressionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * XP and level tracking per user.
 * Level thresholds: level = floor(sqrt(xp / 100)) + 1
 */
#[ORM\Entity(repositoryClass: UserProgressionRepository::class)]
class UserProgression
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'progression')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Users $user = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $xp = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $totalMatches = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $uniqueSpicesUsed = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $discoveries = 0;

    /**
     * @var Collection<int, UserAchievement>
     */
    #[ORM\OneToMany(mappedBy: 'userProgression', targetEntity: UserAchievement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $userAchievements;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->userAchievements = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getXp(): int
    {
        return $this->xp;
    }

    public function addXp(int $amount): static
    {
        $this->xp += max(0, $amount);
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** Level = floor(sqrt(xp / 100)) + 1, capped at 100 */
    public function getLevel(): int
    {
        return min(100, (int) floor(sqrt($this->xp / 100)) + 1);
    }

    /** XP needed to reach next level */
    public function getXpToNextLevel(): int
    {
        $nextLevel = $this->getLevel();

        return ($nextLevel * $nextLevel * 100) - $this->xp;
    }

    public function getTotalMatches(): int
    {
        return $this->totalMatches;
    }

    public function incrementMatches(): static
    {
        ++$this->totalMatches;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getUniqueSpicesUsed(): int
    {
        return $this->uniqueSpicesUsed;
    }

    public function setUniqueSpicesUsed(int $count): static
    {
        $this->uniqueSpicesUsed = $count;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getDiscoveries(): int
    {
        return $this->discoveries;
    }

    public function incrementDiscoveries(): static
    {
        ++$this->discoveries;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @return Collection<int, UserAchievement>
     */
    public function getUserAchievements(): Collection
    {
        return $this->userAchievements;
    }

    public function hasAchievement(Achievement $achievement): bool
    {
        return $this->userAchievements->exists(
            fn (int $_, UserAchievement $ua) => $ua->getAchievement() === $achievement
        );
    }

    public function unlockAchievement(Achievement $achievement): UserAchievement
    {
        $ua = new UserAchievement();
        $ua->setUserProgression($this)->setAchievement($achievement);
        $this->userAchievements->add($ua);

        return $ua;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
