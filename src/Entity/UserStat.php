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
     * @var array<string> slugs of easter eggs already found (idempotence)
     */
    #[ORM\Column(type: 'json', nullable: true, options: [
        'default' => '[]',
    ])]
    private array $foundEggSlugs = [];

    /**
     * Virtual property using PHP 8.4 Property Hooks.
     * Source of truth is `UserProgression` for match/read counters; `UserStat` only owns
     * easter eggs and visited-group tracking.
     */
    public int $totalActions {
        get {
            $progression = $this->user?->getProgression();
            $matches = $progression?->getTotalMatches() ?? 0;
            $spicesRead = $progression?->getTotalSpicesRead() ?? 0;

            return $matches + $spicesRead + $this->easterEggsFound;
        }
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

    /**
     * @return array<string>
     */
    public function getFoundEggSlugs(): array
    {
        return $this->foundEggSlugs;
    }

    public function hasFoundEgg(string $slug): bool
    {
        return \in_array($slug, $this->foundEggSlugs, true);
    }

    public function recordFoundEgg(string $slug): static
    {
        if (! $this->hasFoundEgg($slug)) {
            $this->foundEggSlugs[] = $slug;
        }

        return $this;
    }
}
