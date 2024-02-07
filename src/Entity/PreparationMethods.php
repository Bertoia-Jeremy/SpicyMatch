<?php

namespace App\Entity;

use App\Repository\PreparationMethodsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreparationMethodsRepository::class)]
class PreparationMethods
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $text = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    #[ORM\OneToMany(mappedBy: 'preparationMethod', targetEntity: PreparationTips::class, cascade: ['persist', 'remove'])]
    private Collection $preparationTips;

    public function __construct()
    {
        $this->preparationTips = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @return Collection<int, PreparationTips>
     */
    public function getPreparationTips(): Collection
    {
        return $this->preparationTips;
    }

    public function addPreparationTip(PreparationTips $preparationTip): static
    {
        if (!$this->preparationTips->contains($preparationTip)) {
            $this->preparationTips->add($preparationTip);
            $preparationTip->setPreparationMethod($this);
        }

        return $this;
    }

    public function removePreparationTip(PreparationTips $preparationTip): static
    {
        if ($this->preparationTips->removeElement($preparationTip)) {
            // set the owning side to null (unless already changed)
            if ($preparationTip->getPreparationMethod() === $this) {
                $preparationTip->setPreparationMethod(null);
            }
        }

        return $this;
    }
}
