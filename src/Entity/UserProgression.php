<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserProgressionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * XP and level tracking per user.
 * Level thresholds: level = min(50, floor(sqrt(xp / 10)) + 1)
 * Level 50 requires ~24 010 XP (~40h of interaction).
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

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $xp = 0;

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $totalMatches = 0;

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $uniqueSpicesUsed = 0;

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $discoveries = 0;

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $totalSpicesRead = 0;

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $currentReadingStreak = 0;

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $longestReadingStreak = 0;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastReadDate = null;

    #[ORM\Column(options: [
        'default' => true,
    ])]
    private bool $gamificationEnabled = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserAchievement $equippedBadge = null;

    /**
     * @var Collection<int, UserAchievement>
     */
    #[ORM\OneToMany(mappedBy: 'userProgression', targetEntity: UserAchievement::class, cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
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

    /**
     * Level = min(50, floor(sqrt(xp / 10)) + 1). Level 50 = 24 010 XP.
     */
    public function getLevel(): int
    {
        return min(50, (int) floor(sqrt($this->xp / 10)) + 1);
    }

    /**
     * XP needed to reach next level (0 if already at max level 50).
     */
    public function getXpToNextLevel(): int
    {
        $currentLevel = $this->getLevel();
        if ($currentLevel >= 50) {
            return 0;
        }
        $nextLevel = $currentLevel + 1;

        return ($nextLevel * $nextLevel * 10) - $this->xp;
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
        $ua->setUserProgression($this)
            ->setAchievement($achievement);
        $this->userAchievements->add($ua);

        return $ua;
    }

    public function getTotalSpicesRead(): int
    {
        return $this->totalSpicesRead;
    }

    public function incrementSpicesRead(): static
    {
        ++$this->totalSpicesRead;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCurrentReadingStreak(): int
    {
        return $this->currentReadingStreak;
    }

    public function getLongestReadingStreak(): int
    {
        return $this->longestReadingStreak;
    }

    /**
     * Call once per day when a new spice view is recorded.
     * Increments the streak if last read was yesterday, resets to 1 otherwise.
     */
    public function recordReadingStreak(): static
    {
        $today = new \DateTimeImmutable('today');

        if ($this->lastReadDate === null) {
            $this->currentReadingStreak = 1;
        } else {
            $diff = (int) $today->diff($this->lastReadDate)
                ->days;
            if ($diff === 1) {
                ++$this->currentReadingStreak;
            } elseif ($diff > 1) {
                $this->currentReadingStreak = 1;
            }
            // diff === 0 : already recorded today, no change
        }

        if ($this->currentReadingStreak > $this->longestReadingStreak) {
            $this->longestReadingStreak = $this->currentReadingStreak;
        }

        $this->lastReadDate = $today;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isGamificationEnabled(): bool
    {
        return $this->gamificationEnabled;
    }

    public function enableGamification(): static
    {
        $this->gamificationEnabled = true;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function disableGamification(): static
    {
        $this->gamificationEnabled = false;
        $this->equippedBadge = null;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getEquippedBadge(): ?UserAchievement
    {
        return $this->equippedBadge;
    }

    public function equipBadge(?UserAchievement $ua): static
    {
        if ($ua !== null && $ua->getUserProgression() !== $this) {
            throw new \InvalidArgumentException('Badge does not belong to this user.');
        }
        $this->equippedBadge = $ua;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
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
