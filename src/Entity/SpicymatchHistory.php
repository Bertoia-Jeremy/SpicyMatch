<?php

namespace App\Entity;

use App\Repository\SpicyMatchHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpicyMatchHistoryRepository::class)]
class SpicyMatchHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?SpicyMatch $spicymatch_id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?array $preparation_tips_ids = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?array $cooking_tips_ids = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?int $nb_spice = null;

    #[ORM\Column]
    private ?bool $favorite = null;

    #[ORM\Column]
    private ?bool $finish = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpicymatchId(): ?SpicyMatch
    {
        return $this->spicymatch_id;
    }

    public function setSpicymatchId(?SpicyMatch $spicymatch_id): static
    {
        $this->spicymatch_id = $spicymatch_id;

        return $this;
    }

    public function getPreparationTipsIds(): ?string
    {
        return $this->preparation_tips_ids;
    }

    public function setPreparationTipsIds(?string $preparation_tips_ids): static
    {
        $this->preparation_tips_ids = $preparation_tips_ids;

        return $this;
    }

    public function getCookingTipsIds(): ?string
    {
        return $this->cooking_tips_ids;
    }

    public function setCookingTipsIds(?string $cooking_tips_ids): static
    {
        $this->cooking_tips_ids = $cooking_tips_ids;

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

    public function getNbSpice(): ?int
    {
        return $this->nb_spice;
    }

    public function setNbSpice(?int $nb_spice): static
    {
        $this->nb_spice = $nb_spice;

        return $this;
    }

    public function isFavorite(): ?bool
    {
        return $this->favorite;
    }

    public function setFavorite(bool $favorite): static
    {
        $this->favorite = $favorite;

        return $this;
    }

    public function isFinish(): ?bool
    {
        return $this->finish;
    }

    public function setFinish(bool $finish): static
    {
        $this->finish = $finish;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?\DateTimeInterface $deleted_at): static
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }
}
