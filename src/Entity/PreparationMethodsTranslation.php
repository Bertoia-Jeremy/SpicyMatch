<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslationInterface;
use App\Repository\PreparationMethodsTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Traduction localisée d'une méthode de préparation. Une ligne par (méthode, locale).
 * Tous les champs nullables : fallback FR canonique par champ (COALESCE).
 */
#[ORM\Entity(repositoryClass: PreparationMethodsTranslationRepository::class)]
#[ORM\Table(name: 'preparation_methods_translation')]
#[ORM\UniqueConstraint(name: 'uniq_preparation_methods_locale', columns: ['preparation_methods_id', 'locale'])]
#[ORM\Index(name: 'idx_preparation_methods_translation_locale', columns: ['locale'])]
class PreparationMethodsTranslation implements TranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PreparationMethods::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'preparation_methods_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?PreparationMethods $preparationMethod = null;

    #[ORM\Column(type: 'string', length: 5)]
    private string $locale = 'fr';

    #[ORM\Column(type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $reviewed = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $tools = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $informations = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $advice = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPreparationMethod(): ?PreparationMethods
    {
        return $this->preparationMethod;
    }

    public function setPreparationMethod(?PreparationMethods $preparationMethod): static
    {
        $this->preparationMethod = $preparationMethod;

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

    public function getTools(): ?string
    {
        return $this->tools;
    }

    public function setTools(?string $tools): static
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

    public function setAdvice(?string $advice): static
    {
        $this->advice = $advice;

        return $this;
    }
}
