<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\Sluggable;
use App\Entity\Translation\TranslatableInterface;
use App\Repository\AlchemyFlavorsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlchemyFlavorsRepository::class)]
#[ORM\Table(name: 'alchemy_flavors')]
class AlchemyFlavors implements TranslatableInterface, Sluggable
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
     * @var Collection<int, AromaticCompound>
     */
    #[ORM\ManyToMany(targetEntity: AromaticCompound::class, mappedBy: 'alchemyFlavors')]
    private Collection $aromaticsCompounds;

    /**
     * @var Collection<int, AlchemyFlavorsTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'alchemyFlavor', targetEntity: AlchemyFlavorsTranslation::class, cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->aromaticsCompounds = new ArrayCollection();
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, AlchemyFlavorsTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(AlchemyFlavorsTranslation $translation): self
    {
        if (! $this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setAlchemyFlavor($this);
        }

        return $this;
    }

    public function removeTranslation(AlchemyFlavorsTranslation $translation): self
    {
        if ($this->translations->removeElement($translation) && $translation->getAlchemyFlavor() === $this) {
            $translation->setAlchemyFlavor(null);
        }

        return $this;
    }

    public function getTranslation(string $locale): ?AlchemyFlavorsTranslation
    {
        if ('fr' === $locale) {
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
     * @return Collection<int, AromaticCompound>
     */
    public function getAromaticsCompounds(): Collection
    {
        return $this->aromaticsCompounds;
    }

    public function addAromaticsCompounds(AromaticCompound $aromaticCompounds): self
    {
        if (! $this->aromaticsCompounds->contains($aromaticCompounds)) {
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

    public function __toString(): string
    {
        return $this->name;
    }
}
