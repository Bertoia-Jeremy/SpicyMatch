<?php

namespace App\Entity;

use App\Repository\AlchemyFlavorsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AlchemyFlavorsRepository::class)
 * @ORM\Table(name="alchemy_flavors")
 */
class AlchemyFlavors
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="name", type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(name="cooking", type="text", nullable=true)
     */
    private $cooking;

    /**
     * @ORM\Column(name="informations", type="text", nullable=true)
     */
    private $informations;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     */
    private $created_at;

    /**
     * @ORM\Column(name="updated_at", type="datetime")
     */
    private $updated_at;

    /**
     * @ORM\Column(name="deleted_at", type="datetime", nullable=true)
     */
    private $deleted_at;

    /**
     * @ORM\ManyToMany(targetEntity=AromaticCompound::class, mappedBy="alchemyFlavors")
     * @ORM\JoinColumn(referencedColumnName="id", name="aromaticsCompounds")
     */
    private $aromaticsCompounds;

    public function __construct()
    {
        $this->aromaticsCompounds = new ArrayCollection();
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
     * @return Collection<int, AromaticCompound>
     */
    public function getAromaticsCompounds(): Collection
    {
        return $this->aromaticsCompounds;
    }

    public function addAromaticsCompounds(AromaticCompound $aromaticCompounds): self
    {
        if (!$this->aromaticsCompounds->contains($aromaticCompounds)) {
            $this->aromaticsCompounds[] = $aromaticCompounds;
            $aromaticCompounds->addAlchemyFlavors($this);
        }

        return $this;
    }

    public function removeAromaticsCompounds(AromaticCompound $aromaticCompounds): self
    {
        if ($this->aromaticsCompounds->removeElement($aromaticCompounds)) {
            $aromaticCompounds->removeAlchemyFlavors($this);
        }

        return $this;
    }

    public function __toString()
    {
        return $this->name;
    }
}
