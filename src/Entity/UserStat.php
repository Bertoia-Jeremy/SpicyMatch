<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserStatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserStatRepository::class)]
class UserStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'stats', targetEntity: Users::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Users $user = null;

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
    private int $totalSpicesRead = 0;

    /**
     * @var array<int> list of aromatic group IDs visited
     */
    #[ORM\Column(type: 'json')]
    private array $visitedAromaticGroups = [];

    /**
     * @var array<int> IDs of last few visited spices (for sequence detection)
     */
    #[ORM\Column(type: 'json')]
    private array $lastVisitedSpices = [];

    #[ORM\Column(options: [
        'default' => 0,
    ])]
    private int $easterEggsFound = 0;

    /**
     * Virtual property using PHP 8.4 Property Hooks.
     */
    public int $totalActions {
        get => $this->totalMatches + $this->totalSpicesRead + $this->easterEggsFound;
    }

    /**
     * Count of unique aromatic groups visited.
     */
    public int $visitedGroupsCount {
        get => \count(\array_unique($this->visitedAromaticGroups));
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

    public function getTotalMatches(): int
    {
        return $this->totalMatches;
    }

    public function setTotalMatches(int $totalMatches): static
    {
        $this->totalMatches = $totalMatches;

        return $this;
    }

    public function getUniqueSpicesUsed(): int
    {
        return $this->uniqueSpicesUsed;
    }

    public function setUniqueSpicesUsed(int $uniqueSpicesUsed): static
    {
        $this->uniqueSpicesUsed = $uniqueSpicesUsed;

        return $this;
    }

    public function getTotalSpicesRead(): int
    {
        return $this->totalSpicesRead;
    }

    public function incrementSpicesRead(): static
    {
        ++$this->totalSpicesRead;

        return $this;
    }

    /**
     * @return array<int>
     */
    public function getVisitedAromaticGroups(): array
    {
        return $this->visitedAromaticGroups;
    }

    public function addVisitedAromaticGroup(int $groupId): static
    {
        if (! \in_array($groupId, $this->visitedAromaticGroups, true)) {
            $this->visitedAromaticGroups[] = $groupId;
        }

        return $this;
    }

    /**
     * @return array<int>
     */
    public function getLastVisitedSpices(): array
    {
        return $this->lastVisitedSpices;
    }

    public function recordVisitedSpice(int $spiceId): static
    {
        $this->lastVisitedSpices[] = $spiceId;
        // Keep only last 10
        if (\count($this->lastVisitedSpices) > 10) {
            array_shift($this->lastVisitedSpices);
        }

        return $this;
    }

    public function getEasterEggsFound(): int
    {
        return $this->easterEggsFound;
    }

    public function incrementEasterEggsFound(): static
    {
        ++$this->easterEggsFound;

        return $this;
    }
}
