<?php

namespace App\Entity;

use App\Repository\CookingTipsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CookingTipsRepository::class)]
class CookingTips
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $cooking_step = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $text = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    #[ORM\ManyToOne(inversedBy: 'cookingTips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Spices $spice = null;

    #[ORM\ManyToMany(targetEntity: AlchemyFlavors::class)]
    private Collection $alchemyFlavors;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    public function __construct()
    {
        $this->alchemyFlavors = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCookingStep(): ?string
    {
        return $this->cooking_step;
    }

    public function setCookingStep(string $cooking_step): static
    {
        $this->cooking_step = $cooking_step;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

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

    public function getSpice(): ?Spices
    {
        return $this->spice;
    }

    public function setSpice(?Spices $spice): static
    {
        $this->spice = $spice;

        return $this;
    }

    /**
     * @return Collection<int, AlchemyFlavors>
     */
    public function getAlchemyFlavors(): Collection
    {
        return $this->alchemyFlavors;
    }

    public function addAlchemyFlavor(AlchemyFlavors $alchemyFlavor): static
    {
        if (!$this->alchemyFlavors->contains($alchemyFlavor)) {
            $this->alchemyFlavors->add($alchemyFlavor);
        }

        return $this;
    }

    public function removeAlchemyFlavor(AlchemyFlavors $alchemyFlavor): static
    {
        $this->alchemyFlavors->removeElement($alchemyFlavor);

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
}