<?php

namespace App\Entity;

use App\Entity\Translation\TranslatableInterface;
use App\Enum\OdtMatrix;
use App\Repository\CookingTipsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CookingTipsRepository::class)]
class CookingTips implements TranslatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $cooking_step = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $text = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    #[ORM\ManyToOne(inversedBy: 'cookingTips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Spices $spice = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column]
    private ?int $step = null;

    #[ORM\Column(length: 255)]
    private ?string $advantages = null;

    /**
     * Matrice culinaire pour laquelle ce conseil s'applique.
     * null = s'applique à toutes les matrices.
     */
    #[ORM\Column(name: 'applicable_matrix', type: 'string', length: 5, nullable: true, enumType: OdtMatrix::class)]
    private ?OdtMatrix $applicableMatrix = null;

    /**
     * @var Collection<int, CookingTipsTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'cookingTip', targetEntity: CookingTipsTranslation::class, cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, CookingTipsTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(CookingTipsTranslation $translation): static
    {
        if (! $this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setCookingTip($this);
        }

        return $this;
    }

    public function removeTranslation(CookingTipsTranslation $translation): static
    {
        if ($this->translations->removeElement($translation) && $translation->getCookingTip() === $this) {
            $translation->setCookingTip(null);
        }

        return $this;
    }

    public function getTranslation(string $locale): ?CookingTipsTranslation
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

    public function getLocalizedCookingStep(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getCookingStep() ?? $this->cooking_step;
    }

    public function getLocalizedText(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getText() ?? $this->text;
    }

    public function getLocalizedTitle(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getTitle() ?? $this->title;
    }

    public function getLocalizedAdvantages(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getAdvantages() ?? $this->advantages;
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStep(): ?int
    {
        return $this->step;
    }

    public function setStep(int $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function getAdvantages(): ?string
    {
        return $this->advantages;
    }

    public function setAdvantages(string $advantages): static
    {
        $this->advantages = $advantages;

        return $this;
    }

    public function getApplicableMatrix(): ?OdtMatrix
    {
        return $this->applicableMatrix;
    }

    public function setApplicableMatrix(?OdtMatrix $applicableMatrix): static
    {
        $this->applicableMatrix = $applicableMatrix;

        return $this;
    }
}
