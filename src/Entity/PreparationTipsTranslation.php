<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslationInterface;
use App\Repository\PreparationTipsTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Traduction localisée d'un conseil de préparation. Une ligne par (conseil, locale).
 * Tous les champs nullables : fallback FR canonique par champ (COALESCE).
 */
#[ORM\Entity(repositoryClass: PreparationTipsTranslationRepository::class)]
#[ORM\Table(name: 'preparation_tips_translation')]
#[ORM\UniqueConstraint(name: 'uniq_preparation_tips_locale', columns: ['preparation_tips_id', 'locale'])]
#[ORM\Index(name: 'idx_preparation_tips_translation_locale', columns: ['locale'])]
class PreparationTipsTranslation implements TranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PreparationTips::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'preparation_tips_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?PreparationTips $preparationTip = null;

    #[ORM\Column(type: 'string', length: 5)]
    private string $locale = 'fr';

    #[ORM\Column(type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $reviewed = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $text = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $advantages = null;

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

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;

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

    public function getAdvantages(): ?string
    {
        return $this->advantages;
    }

    public function setAdvantages(?string $advantages): static
    {
        $this->advantages = $advantages;

        return $this;
    }
}
