<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AromaticCompoundRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AromaticCompoundRepository::class)]
#[ORM\Table(name: 'aromatic_compound')]
// Levier 1 — le CAS est l'identité universelle : unicité garantie en base.
// MySQL/MariaDB autorise plusieurs NULL dans un index UNIQUE → les composés
// pas encore renseignés ne bloquent pas (mais deux mêmes CAS = erreur).
#[ORM\UniqueConstraint(name: 'uniq_aromatic_compound_cas', columns: ['cas_number'])]
class AromaticCompound
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private ?string $name = null;

    /**
     * Numéro CAS — identifiant universel cross-sources (PubChem, van Gemert, FlavorDB, Flavornet).
     * Format : XXXXXXX-YY-Z (ex: "97-53-0" pour l'eugénol).
     * Nullable : non renseigné tant que la validation PubChem n'est pas effectuée.
     */
    #[ORM\Column(name: 'cas_number', type: 'string', length: 50, nullable: true)]
    private ?string $casNumber = null;

    /**
     * Formule brute (ex: "C10H12O2").
     * Source : PubChem — validé via NIST WebBook.
     */
    #[ORM\Column(name: 'formula', type: 'string', length: 30, nullable: true)]
    private ?string $formula = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'cooking', type: 'text', nullable: true)]
    private ?string $cooking = null;

    #[ORM\Column(name: 'informations', type: 'text', nullable: true)]
    private ?string $informations = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    /**
     * @var Collection<int, Spices>
     */
    #[ORM\ManyToMany(targetEntity: Spices::class, mappedBy: 'aromaticsCompounds')]
    private Collection $spices;

    /**
     * @var Collection<int, AlchemyFlavors>
     */
    #[ORM\ManyToMany(targetEntity: AlchemyFlavors::class, inversedBy: 'aromaticsCompounds')]
    private Collection $alchemyFlavors;

    /**
     * @var Collection<int, Spices>
     */
    #[ORM\ManyToMany(targetEntity: Spices::class, mappedBy: 'secondary_aromatics_compounds')]
    private Collection $secondary_spices;

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

    public function getCasNumber(): ?string
    {
        return $this->casNumber;
    }

    public function setCasNumber(?string $casNumber): self
    {
        $this->casNumber = $casNumber;

        return $this;
    }

    public function getFormula(): ?string
    {
        return $this->formula;
    }

    public function setFormula(?string $formula): self
    {
        $this->formula = $formula;

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
