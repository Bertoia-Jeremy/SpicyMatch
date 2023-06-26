<?php

namespace App\Entity;

use App\Repository\AromaticCompoundRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=AromaticCompoundRepository::class)
 * @ORM\Table(name="aromatic_compound")
 */
class AromaticCompound
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
     * @ORM\ManyToMany(targetEntity=Spices::class, mappedBy="aromaticsCompounds")
     * @ORM\JoinColumn(referencedColumnName="id", name="spices")
     */
    private $spices;

    /**
     * @ORM\ManyToMany(targetEntity=AlchemyFlavors::class, inversedBy="aromaticsCompounds")
     * @ORM\JoinColumn(referencedColumnName="id", name="alchemyFlavors")
     */
    private $alchemyFlavors;

    public function __construct()
    {
        $this->spices = new ArrayCollection();
        $this->alchemyFlavors = new ArrayCollection();
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
     * @return Collection<int, Spices>
     */
    public function getSpices(): Collection
    {
        return $this->spices;
    }

    public function addSpices(Spices $spices): self
    {
        if (!$this->spices->contains($spices)) {
            $this->spices[] = $spices;
            $spices->addAromaticsCompounds($this);
        }

        return $this;
    }

    public function removeSpices(Spices $spices): self
    {
        if ($this->spices->removeElement($spices)) {
            $spices->removeAromaticsCompounds($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, AlchemyFlavors>
     */
    public function getAlchemyFlavors(): Collection
    {
        return $this->alchemyFlavors;
    }

    public function addAlchemyFlavors(AlchemyFlavors $alchemyFlavors): self
    {
        if (!$this->alchemyFlavors->contains($alchemyFlavors)) {
            $this->alchemyFlavors[] = $alchemyFlavors;
        }

        return $this;
    }

    public function removeAlchemyFlavors(AlchemyFlavors $alchemyFlavors): self
    {
        $this->alchemyFlavors->removeElement($alchemyFlavors);

        return $this;
    }

    public function __toString()
    {
        return $this->name;
    }
}
