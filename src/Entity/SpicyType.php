<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\Sluggable;
use App\Entity\Translation\TranslatableInterface;
use App\Repository\SpicyTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpicyTypeRepository::class)]
#[ORM\Table(name: 'spicy_type')]
class SpicyType implements TranslatableInterface, Sluggable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private ?string $slug = null;

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
    #[ORM\OneToMany(targetEntity: Spices::class, mappedBy: 'spicyType')]
    private Collection $spices;

    /**
     * @var Collection<int, SpicyTypeTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'spicyType', targetEntity: SpicyTypeTranslation::class, cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->spices = new ArrayCollection();
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, SpicyTypeTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(SpicyTypeTranslation $translation): self
    {
        if (! $this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setSpicyType($this);
        }

        return $this;
    }

    public function removeTranslation(SpicyTypeTranslation $translation): self
    {
        if ($this->translations->removeElement($translation) && $translation->getSpicyType() === $this) {
            $translation->setSpicyType(null);
        }

        return $this;
    }

    public function getTranslation(string $locale): ?SpicyTypeTranslation
    {
        if ($locale === 'fr') {
            return null;
        }

        foreach ($this->translations as $t) {
            if ($t->getLocale() === $locale) {
                return $t;
            }
        }

        return null;
    }

    public function getLocalizedName(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getName() ?? $this->name;
    }

    public function getLocalizedDescription(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getDescription() ?? $this->description;
    }

    public function getLocalizedCooking(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getCooking() ?? $this->cooking;
    }

    public function getLocalizedInformations(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getInformations() ?? $this->informations;
    }

    public function getLocalizedSlug(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getSlug() ?? $this->slug;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
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

    public function addSpice(Spices $spice): self
    {
        if (! $this->spices->contains($spice)) {
            $this->spices[] = $spice;
            $spice->setSpicyType($this);
        }

        return $this;
    }

    public function removeSpice(Spices $spice): self
    {
        // set the owning side to null (unless already changed)
        if ($this->spices->removeElement($spice) && $spice->getSpicyType() === $this) {
            $spice->setSpicyType(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
