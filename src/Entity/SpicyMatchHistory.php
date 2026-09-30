<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpicyMatchHistoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SpicyMatchHistoryRepository::class)]
#[ORM\Index(name: 'idx_smh_spicy_match', columns: ['spicy_match_id'])]
#[ORM\Index(name: 'idx_smh_created_deleted', columns: ['spicy_match_id', 'deleted_at', 'created_at'])]
#[ORM\Index(name: 'idx_smh_favorite_deleted', columns: ['spicy_match_id', 'favorite', 'deleted_at'])]
class SpicyMatchHistory
{
    public const int TITLE_MAX_LENGTH = 120;

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
    #[Assert\Length(max: self::TITLE_MAX_LENGTH)]
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

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sealedAt = null;

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

    public function chooseCookingTip(Spices $spice, ?CookingTips $tip): void
    {
        if ($tip !== null && $tip->getSpice() !== $spice) {
            throw new \InvalidArgumentException('Cooking tip does not belong to this spice.');
        }

        foreach ($this->cookingTips->toArray() as $current) {
            if ($current->getSpice() === $spice && $current !== $tip) {
                $this->cookingTips->removeElement($current);
            }
        }
        if ($tip !== null) {
            $this->addCookingTip($tip);
        }
    }

    public function choosePreparationTip(Spices $spice, ?PreparationTips $tip): void
    {
        if ($tip !== null && $tip->getSpice() !== $spice) {
            throw new \InvalidArgumentException('Preparation tip does not belong to this spice.');
        }

        foreach ($this->preparationTips->toArray() as $current) {
            if ($current->getSpice() === $spice && $current !== $tip) {
                $this->preparationTips->removeElement($current);
            }
        }
        if ($tip !== null) {
            $this->addPreparationTip($tip);
        }
    }

    public function markSealedIfComplete(\DateTimeImmutable $now): bool
    {
        if ($this->sealedAt !== null || ! $this->isSealed()) {
            return false;
        }

        $this->sealedAt = $now;

        return true;
    }

    public function getSealedAt(): ?\DateTimeImmutable
    {
        return $this->sealedAt;
    }

    public function isSealed(): bool
    {
        $spices = $this->spicyMatch?->getSpices();
        if ($spices === null || $spices->isEmpty()) {
            return false;
        }

        foreach ($spices as $spice) {
            if (! $this->cookingTips->exists(static fn (int $key, CookingTips $tip): bool => $tip->getSpice() === $spice)
                || ! $this->preparationTips->exists(static fn (int $key, PreparationTips $tip): bool => $tip->getSpice() === $spice)) {
                return false;
            }
        }

        return true;
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
