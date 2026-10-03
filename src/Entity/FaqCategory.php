<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslatableInterface;
use App\Repository\FaqCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FaqCategoryRepository::class)]
#[ORM\Table(name: 'faq_category')]
#[UniqueEntity(fields: ['code'])]
class FaqCategory implements TranslatableInterface, \Stringable
{
    public const string CODE_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: self::CODE_PATTERN)]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'integer', options: [
        'default' => 0,
    ])]
    private int $position = 0;

    /**
     * @var Collection<int, FaqCategoryTranslation>
     */
    #[ORM\OneToMany(targetEntity: FaqCategoryTranslation::class, mappedBy: 'category', cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    #[Assert\Valid]
    #[Assert\Unique(normalizer: static function (FaqCategoryTranslation $translation): string { return $translation->getLocale(); }, errorPath: 'locale')]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return Collection<int, FaqCategoryTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(FaqCategoryTranslation $translation): static
    {
        if (! $this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setCategory($this);
        }

        return $this;
    }

    public function removeTranslation(FaqCategoryTranslation $translation): static
    {
        if ($this->translations->removeElement($translation) && $translation->getCategory() === $this) {
            $translation->setCategory(null);
        }

        return $this;
    }

    public function getTranslation(string $locale): ?FaqCategoryTranslation
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

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
