<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslatableInterface;
use App\Repository\SpiceDuoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: SpiceDuoRepository::class)]
#[ORM\Table(name: 'spice_duo')]
#[ORM\UniqueConstraint(name: 'uniq_spice_duo_tips', columns: ['preparation_tip_id', 'cooking_tip_id'])]
#[UniqueEntity(fields: ['preparationTip', 'cookingTip'], message: 'spice_duo.duplicate')]
class SpiceDuo implements TranslatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PreparationTips::class)]
    #[ORM\JoinColumn(name: 'preparation_tip_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?PreparationTips $preparationTip = null;

    #[ORM\ManyToOne(targetEntity: CookingTips::class)]
    #[ORM\JoinColumn(name: 'cooking_tip_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?CookingTips $cookingTip = null;

    #[ORM\Column(name: '`rank`', type: Types::SMALLINT, options: [
        'default' => 1,
    ])]
    #[Assert\Range(min: 1, max: 9)]
    private int $rank = 1;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $effect = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $science = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $example = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    /**
     * @var Collection<int, SpiceDuoTranslation>
     */
    #[ORM\OneToMany(targetEntity: SpiceDuoTranslation::class, mappedBy: 'duo', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validateSameSpice(ExecutionContextInterface $context): void
    {
        $prepSpice = $this->preparationTip?->getSpice();
        $cookSpice = $this->cookingTip?->getSpice();

        if ($prepSpice !== null && $cookSpice !== null && $prepSpice !== $cookSpice) {
            $context->buildViolation('spice_duo.spice_mismatch')
                ->atPath('cookingTip')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPreparationTip(): ?PreparationTips
    {
        return $this->preparationTip;
    }

    public function setPreparationTip(?PreparationTips $preparationTip): static
    {
        $this->preparationTip = $preparationTip;

        return $this;
    }

    public function getCookingTip(): ?CookingTips
    {
        return $this->cookingTip;
    }

    public function setCookingTip(?CookingTips $cookingTip): static
    {
        $this->cookingTip = $cookingTip;

        return $this;
    }

    public function getSpice(): ?Spices
    {
        return $this->preparationTip?->getSpice();
    }

    public function getRank(): int
    {
        return $this->rank;
    }

    public function setRank(int $rank): static
    {
        $this->rank = $rank;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getEffect(): ?string
    {
        return $this->effect;
    }

    public function setEffect(string $effect): static
    {
        $this->effect = $effect;

        return $this;
    }

    public function getScience(): ?string
    {
        return $this->science;
    }

    public function setScience(string $science): static
    {
        $this->science = $science;

        return $this;
    }

    public function getExample(): ?string
    {
        return $this->example;
    }

    public function setExample(string $example): static
    {
        $this->example = $example;

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

    /**
     * @return Collection<int, SpiceDuoTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(SpiceDuoTranslation $translation): static
    {
        if (! $this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setDuo($this);
        }

        return $this;
    }

    public function removeTranslation(SpiceDuoTranslation $translation): static
    {
        if ($this->translations->removeElement($translation) && $translation->getDuo() === $this) {
            $translation->setDuo(null);
        }

        return $this;
    }

    public function getTranslation(string $locale): ?SpiceDuoTranslation
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

    public function getLocalizedTitle(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getTitle() ?? $this->title;
    }

    public function getLocalizedEffect(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getEffect() ?? $this->effect;
    }

    public function getLocalizedScience(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getScience() ?? $this->science;
    }

    public function getLocalizedExample(string $locale): ?string
    {
        return $this->getTranslation($locale)?->getExample() ?? $this->example;
    }

    public function getLabel(): string
    {
        return \sprintf(
            '%s — %s × %s',
            $this->getSpice()?->getName() ?? '?',
            $this->preparationTip?->getTitle() ?? '?',
            $this->cookingTip?->getMoment()
                ->name ?? '?',
        );
    }
}
