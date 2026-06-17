<?php

namespace App\Entity;

use App\Entity\Translation\Sluggable;
use App\Entity\Translation\TranslatableInterface;
use App\Repository\PreparationMethodsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PreparationMethodsRepository::class)]
class PreparationMethods implements TranslatableInterface, Sluggable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    /**
     * @var Collection<int, PreparationTips>
     */
    #[ORM\OneToMany(
        mappedBy: 'preparationMethod',
        targetEntity: PreparationTips::class,
        cascade: ['persist', 'remove'])]
    private Collection $preparationTips;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $tools = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $informations = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $advice = null;

    /**
     * @var Collection<int, PreparationMethodsTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'preparationMethod', targetEntity: PreparationMethodsTranslation::class, cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->preparationTips = new ArrayCollection();
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, PreparationMethodsTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(PreparationMethodsTranslation $translation): static
    {
        if (! $this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setPreparationMethod($this);
        }

        return $this;
    }

    public function removeTranslation(PreparationMethodsTranslation $translation): static
    {
        if ($this->translations->removeElement($translation) && $translation->getPreparationMethod() === $this) {
            $translation->setPreparationMethod(null);
        }

        return $this;
    }

    public function getTranslation(string $locale): ?PreparationMethodsTranslation
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

    public function getLocalizedTools(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getTools() ?? $this->tools;
    }

    public function getLocalizedInformations(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getInformations() ?? $this->informations;
    }

    public function getLocalizedAdvice(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getAdvice() ?? $this->advice;
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
        if (! $this->preparationTips->contains($preparationTip)) {
            $this->preparationTips->add($preparationTip);
            $preparationTip->setPreparationMethod($this);
        }

        return $this;
    }

    public function removePreparationTip(PreparationTips $preparationTip): static
    {
        // set the owning side to null (unless already changed)
        if ($this->preparationTips->removeElement(
            $preparationTip
        ) && $preparationTip->getPreparationMethod() === $this) {
            $preparationTip->setPreparationMethod(null);
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTools(): ?string
    {
        return $this->tools;
    }

    public function setTools(string $tools): static
    {
        $this->tools = $tools;

        return $this;
    }

    public function getInformations(): ?string
    {
        return $this->informations;
    }

    public function setInformations(?string $informations): static
    {
        $this->informations = $informations;

        return $this;
    }

    public function getAdvice(): ?string
    {
        return $this->advice;
    }

    public function setAdvice(string $advice): static
    {
        $this->advice = $advice;

        return $this;
    }
}
