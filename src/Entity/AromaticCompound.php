<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AromaticCompoundRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AromaticCompoundRepository::class)]
#[ORM\Table(name: 'aromatic_compound')]
class AromaticCompound
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'cooking', type: 'text', nullable: true)]
    private ?string $cooking = null;

    #[ORM\Column(name: 'informations', type: 'text', nullable: true)]
    private ?string $informations = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    #[ORM\ManyToMany(targetEntity: Spices::class, mappedBy: 'aromaticsCompounds')]
    #[ORM\JoinColumn(referencedColumnName: 'id', name: 'spices')]
    private \Doctrine\Common\Collections\ArrayCollection|array $spices;

    #[ORM\ManyToMany(targetEntity: AlchemyFlavors::class, inversedBy: 'aromaticsCompounds')]
    #[ORM\JoinColumn(referencedColumnName: 'id', name: 'alchemyFlavors')]
    private \Doctrine\Common\Collections\ArrayCollection|array $alchemyFlavors;

    #[ORM\ManyToMany(targetEntity: Spices::class, mappedBy: 'secondary_aromatics_compounds')]
    #[ORM\JoinColumn(referencedColumnName: 'id', name: 'secondarySpices')]
    #[ORM\JoinTable(name: 'secondary_spices_aromatic_compound')]
    private \Doctrine\Common\Collections\ArrayCollection|array $secondary_spices;

    public function __construct()
    {
        $this->spices = new ArrayCollection();
        $this->alchemyFlavors = new ArrayCollection();
        $this->secondary_spices = new ArrayCollection();
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
        if (! $this->spices->contains($spices)) {
            $this->spices[] = $spices;
            $spices->addAromaticCompound($this);
        }

        return $this;
    }

    public function removeSpices(Spices $spices): self
    {
        if ($this->spices->removeElement($spices)) {
            $spices->removeAromaticCompound($this);
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
        if (! $this->alchemyFlavors->contains($alchemyFlavors)) {
            $this->alchemyFlavors[] = $alchemyFlavors;
        }

        return $this;
    }

    public function removeAlchemyFlavors(AlchemyFlavors $alchemyFlavors): self
    {
        $this->alchemyFlavors->removeElement($alchemyFlavors);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @return Collection<int, Spices>
     */
    public function getSecondarySpices(): Collection
    {
        return $this->secondary_spices;
    }

    public function addSecondarySpice(Spices $secondarySpice): self
    {
        if (! $this->secondary_spices->contains($secondarySpice)) {
            $this->secondary_spices[] = $secondarySpice;
            $secondarySpice->addSecondaryAromaticsCompound($this);
        }

        return $this;
    }

    public function removeSecondarySpice(Spices $secondarySpice): self
    {
        if ($this->secondary_spices->removeElement($secondarySpice)) {
            $secondarySpice->removeSecondaryAromaticsCompound($this);
        }

        return $this;
    }
}
