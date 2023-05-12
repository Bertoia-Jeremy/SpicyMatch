<?php

namespace App\Entity;

use App\Repository\AromaticGroupsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AromaticGroupsRepository::class)
 * @ORM\Table(name="agr_aromatic_groups")
 */
class AromaticGroups
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="agr_id", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="agr_name", type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(name="agr_color", type="string", length=255)
     */
    private $color;

    /**
     * @ORM\Column(name="agr_description", type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(name="agr_cooking", type="text", nullable=true)
     */
    private $cooking;

    /**
     * @ORM\Column(name="agr_informations", type="text", nullable=true)
     */
    private $informations;

    /**
     * @ORM\Column(name="agr_created_at", type="datetime")
     */
    private $created_at;

    /**
     * @ORM\Column(name="agr_updated_at", type="datetime")
     */
    private $updated_at;

    /**
     * @ORM\Column(name="agr_deleted_at", type="datetime", nullable=true)
     */
    private $deleted_at;

    /**
     * @ORM\OneToMany(targetEntity=Spices::class, mappedBy="agr_id", orphanRemoval=true)
     */
    private $spices;

    public function __construct()
    {
        $this->spices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCooking(): ?string
    {
        return $this->cooking;
    }

    public function setCooking(?string $cooking): self
    {
        $this->cooking = $cooking;

        return $this;
    }

    public function getInformations(): ?string
    {
        return $this->informations;
    }

    public function setInformations(?string $informations): self
    {
        $this->informations = $informations;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?\DateTimeInterface $deleted_at): self
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    /**
     * @return Collection<int, Spices>
     */
    public function getSpices(): Collection
    {
        return $this->spices;
    }

    public function addSpice(Spices $spice): self
    {
        if (!$this->spices->contains($spice)) {
            $this->spices[] = $spice;
            $spice->setAgrId($this);
        }

        return $this;
    }

    public function removeSpice(Spices $spice): self
    {
        if ($this->spices->removeElement($spice)) {
            // set the owning side to null (unless already changed)
            if ($spice->getAgrId() === $this) {
                $spice->setAgrId(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return $this->name;
    }
}
