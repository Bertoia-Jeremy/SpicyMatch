<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslationInterface;
use App\Repository\SpiceTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Traduction localisée d'une épice (Spices). Une ligne par (spice, locale).
 *
 * Le FR canonique vit sur Spices ; cette table ne contient que les locales
 * traduites. Contrainte unique (spice_id, locale) = intégrité forte (pas de
 * doublon silencieux). Pas de FK contraignante côté DB nécessaire au-delà du
 * JoinColumn standard.
 */
#[ORM\Entity(repositoryClass: SpiceTranslationRepository::class)]
#[ORM\Table(name: 'spice_translation')]
#[ORM\UniqueConstraint(name: 'uniq_spice_locale', columns: ['spice_id', 'locale'])]
#[ORM\Index(name: 'idx_spice_translation_locale', columns: ['locale'])]
class SpiceTranslation implements TranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Spices::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'spice_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Spices $spice = null;

    #[ORM\Column(type: 'string', length: 5)]
    private string $locale = 'fr';

    #[ORM\Column(type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $reviewed = false;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cooking = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $informations = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $benefits = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getBenefits(): ?string
    {
        return $this->benefits;
    }

    public function setBenefits(?string $benefits): static
    {
        $this->benefits = $benefits;

        return $this;
    }
}
