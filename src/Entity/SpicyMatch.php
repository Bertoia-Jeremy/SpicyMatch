<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpicyMatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpicyMatchRepository::class)]
class SpicyMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'spicyMatches')]
    #[ORM\JoinColumn(nullable: false, name: 'user_id')]
    private ?Users $user = null;

    /**
     * @var Collection<int, Spices>
     */
    #[ORM\ManyToMany(targetEntity: Spices::class)]
    #[ORM\JoinTable(name: 'spicy_match_spices')]
    private Collection $spices;

    /**
     * @var Collection<int, SpicyMatchResult>
     */
    #[ORM\OneToMany(mappedBy: 'spicyMatch', targetEntity: SpicyMatchResult::class, cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    private Collection $results;

    /**
     * @var Collection<int, SpicyMatchHistory>
     */
    #[ORM\OneToMany(mappedBy: 'spicyMatch', targetEntity: SpicyMatchHistory::class, cascade: [
        'remove',
    ], orphanRemoval: true)]
    private Collection $spicyMatchHistories;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->spices = new ArrayCollection();
        $this->results = new ArrayCollection();
        $this->spicyMatchHistories = new ArrayCollection();
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

    /**
     * @deprecated Use getUser()
     */
    public function getUserId(): ?Users
    {
        return $this->user;
    }

    /**
     * @deprecated Use setUser()
     */
    public function setUserId(?Users $user): static
    {
        return $this->setUser($user);
    }

    /**
     * @return Collection<int, Spices>
     */
    public function getSpices(): Collection
    {
        return $this->spices;
    }

    public function addSpice(Spices $spice): static
    {
        if (! $this->spices->contains($spice)) {
            $this->spices->add($spice);
        }

        return $this;
    }

    public function removeSpice(Spices $spice): static
    {
        $this->spices->removeElement($spice);

        return $this;
    }

    public function getSpiceCount(): int
    {
        return $this->spices->count();
    }

    /**
     * @return Collection<int, SpicyMatchResult>
     */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function addResult(SpicyMatchResult $result): static
    {
        if (! $this->results->contains($result)) {
            $this->results->add($result);
            $result->setSpicyMatch($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, SpicyMatchHistory>
     */
    public function getSpicyMatchHistories(): Collection
    {
        return $this->spicyMatchHistories;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }
}
