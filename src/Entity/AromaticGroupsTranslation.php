<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslationInterface;
use App\Repository\AromaticGroupsTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Traduction localisée d'un groupe aromatique. Une ligne par (groupe, locale).
 * Le FR canonique vit sur AromaticGroups et sert de fallback (COALESCE).
 */
#[ORM\Entity(repositoryClass: AromaticGroupsTranslationRepository::class)]
#[ORM\Table(name: 'aromatic_groups_translation')]
#[ORM\UniqueConstraint(name: 'uniq_aromatic_groups_locale', columns: ['aromatic_groups_id', 'locale'])]
#[ORM\Index(name: 'idx_aromatic_groups_translation_locale', columns: ['locale'])]
class AromaticGroupsTranslation implements TranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AromaticGroups::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'aromatic_groups_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?AromaticGroups $aromaticGroup = null;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAromaticGroup(): ?AromaticGroups
    {
        return $this->aromaticGroup;
    }

    public function setAromaticGroup(?AromaticGroups $aromaticGroup): static
    {
        $this->aromaticGroup = $aromaticGroup;

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
}
