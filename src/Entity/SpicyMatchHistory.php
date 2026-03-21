<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpicyMatchHistoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpicyMatchHistoryRepository::class)]
class SpicyMatchHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'spicyMatchHistories')]
    #[ORM\JoinColumn(nullable: false, name: 'spicy_match_id')]
    private ?SpicyMatch $spicyMatch = null;

    /**
     * @var Collection<int, PreparationTips>
     */
    #[ORM\ManyToMany(targetEntity: PreparationTips::class)]
    #[ORM\JoinTable(name: 'spicy_match_history_preparation_tips')]
    private Collection $preparationTips;

    /**
     * @var Collection<int, CookingTips>
     */
    #[ORM\ManyToMany(targetEntity: CookingTips::class)]
    #[ORM\JoinTable(name: 'spicy_match_history_cooking_tips')]
    private Collection $cookingTips;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(options: [
        'default' => false,
    ])]
    private bool $favorite = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->preparationTips = new ArrayCollection();
        $this->cookingTips = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpicyMatch(): ?SpicyMatch
    {
        return $this->spicyMatch;
    }

    public function setSpicyMatch(?SpicyMatch $spicyMatch): static
    {
        $this->spicyMatch = $spicyMatch;

        return $this;
    }

    /**
     * @deprecated Use getSpicyMatch()
     */
    public function getSpicyMatchId(): ?SpicyMatch
    {
        return $this->spicyMatch;
    }

    /**
     * @return Collection<int, PreparationTips>
     */
    public function getPreparationTips(): Collection
    {
        return $this->preparationTips;
    }

    public function addPreparationTip(PreparationTips $tip): static
    {
        if (! $this->preparationTips->contains($tip)) {
            $this->preparationTips->add($tip);
        }

        return $this;
    }

    public function removePreparationTip(PreparationTips $tip): static
    {
        $this->preparationTips->removeElement($tip);

        return $this;
    }

    /**
     * @return Collection<int, CookingTips>
     */
    public function getCookingTips(): Collection
    {
        return $this->cookingTips;
    }

    public function addCookingTip(CookingTips $tip): static
    {
        if (! $this->cookingTips->contains($tip)) {
            $this->cookingTips->add($tip);
        }

        return $this;
    }

    public function removeCookingTip(CookingTips $tip): static
    {
        $this->cookingTips->removeElement($tip);

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function isFavorite(): bool
    {
        return $this->favorite;
    }

    public function setFavorite(bool $favorite): static
    {
        $this->favorite = $favorite;

        return $this;
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
