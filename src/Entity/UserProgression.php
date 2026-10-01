<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserProgressionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\Column(name: 'current_reading_streak', options: [
        'default' => 0,
    ])]
    private int $currentActivityStreak = 0;

    #[ORM\Column(name: 'longest_reading_streak', options: [
        'default' => 0,
    ])]
    private int $longestActivityStreak = 0;

    #[ORM\Column(name: 'last_read_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastActivityDate = null;

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
    #[ORM\OneToMany(targetEntity: UserAchievement::class, mappedBy: 'userProgression', cascade: [
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

    public function setXp(int $xp): static
    {
        $this->xp = max(0, $xp);
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function addXp(int $amount): static
    {
        $this->xp += max(0, $amount);
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public int $level {
        get {
            if ($this->xp === 0) {
                return 1;
            }
            $calculated = (int) floor(($this->xp / 100) ** (1 / 1.3));

            return max(1, $calculated);
        }
    }

    public int $xpToNextLevel {
        get {
            $nextLevel = $this->level + 1;
            $requiredXp = (int) ceil(100 * $nextLevel ** 1.3);

            return max(0, $requiredXp - $this->xp);
        }
    }

    public float $progressPercent {
        get {
            $currentLevel = $this->level;
            $xpForCurrent = $currentLevel <= 1 ? 0 : (int) ceil(100 * $currentLevel ** 1.3);
            $xpForNext = (int) ceil(100 * ($currentLevel + 1) ** 1.3);
            $range = $xpForNext - $xpForCurrent;

            if ($range <= 0) {
                return 100.0;
            }

            return round(($this->xp - $xpForCurrent) / $range * 100, 1);
        }
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getXpToNextLevel(): int
    {
        return $this->xpToNextLevel;
    }

    public function getProgressPercent(): float
    {
        return $this->progressPercent;
    }

    public function getTotalMatches(): int
    {
        return $this->totalMatches;
    }

    public function setTotalMatches(int $count): static
    {
        $this->totalMatches = $count;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
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

    public function setDiscoveries(int $count): static
    {
        $this->discoveries = $count;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
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
            fn (int $_, UserAchievement $ua): bool => $ua->getAchievement() === $achievement
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

    public function setTotalSpicesRead(int $count): static
    {
        $this->totalSpicesRead = $count;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCurrentActivityStreak(): int
    {
        return $this->currentActivityStreak;
    }

    public function getLongestActivityStreak(): int
    {
        return $this->longestActivityStreak;
    }

    public function recordActivityStreak(\DateTimeImmutable $today): static
    {
        $today = $today->setTime(0, 0);

        if (! $this->lastActivityDate instanceof \DateTimeImmutable) {
            $this->currentActivityStreak = 1;
        } else {
            $last = $this->lastActivityDate->setTime(0, 0);
            if ($last >= $today) {
                return $this;
            }

            $diff = (int) $today->diff($last)
                ->days;
            $this->currentActivityStreak = $diff === 1 ? $this->currentActivityStreak + 1 : 1;
        }

        if ($this->currentActivityStreak > $this->longestActivityStreak) {
            $this->longestActivityStreak = $this->currentActivityStreak;
        }

        $this->lastActivityDate = $today;
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
        if ($ua instanceof UserAchievement && $ua->getUserProgression() !== $this) {
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
