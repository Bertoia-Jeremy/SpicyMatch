<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\Sluggable;
use App\Entity\Translation\TranslationInterface;
use App\Repository\AromaticCompoundTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Traduction localisée d'un composé aromatique. Une ligne par (composé, locale).
 * cas_number / formula restent invariants sur AromaticCompound (jamais traduits).
 */
#[ORM\Entity(repositoryClass: AromaticCompoundTranslationRepository::class)]
#[ORM\Table(name: 'aromatic_compound_translation')]
#[ORM\UniqueConstraint(name: 'uniq_aromatic_compound_locale', columns: ['aromatic_compound_id', 'locale'])]
#[ORM\UniqueConstraint(name: 'uniq_aromatic_compound_translation_slug', columns: ['locale', 'slug'])]
#[ORM\Index(name: 'idx_aromatic_compound_translation_locale', columns: ['locale'])]
class AromaticCompoundTranslation implements TranslationInterface, Sluggable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AromaticCompound::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'aromatic_compound_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AromaticCompound $aromaticCompound = null;

    #[ORM\Column(type: 'string', length: 5)]
    private string $locale = 'fr';

    #[ORM\Column(type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $reviewed = false;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cooking = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $informations = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAromaticCompound(): ?AromaticCompound
    {
        return $this->aromaticCompound;
    }

    public function setAromaticCompound(?AromaticCompound $aromaticCompound): static
    {
        $this->aromaticCompound = $aromaticCompound;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function isReviewed(): bool
    {
        return $this->reviewed;
    }

    public function setReviewed(bool $reviewed): static
    {
        $this->reviewed = $reviewed;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCooking(): ?string
    {
        return $this->cooking;
    }

    public function setCooking(?string $cooking): static
    {
        $this->cooking = $cooking;

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
}
