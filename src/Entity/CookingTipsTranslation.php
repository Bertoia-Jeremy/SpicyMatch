<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslationInterface;
use App\Repository\CookingTipsTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Traduction localisée d'un conseil de cuisson. Une ligne par (conseil, locale).
 * Tous les champs nullables : un champ absent retombe sur le FR canonique (COALESCE).
 */
#[ORM\Entity(repositoryClass: CookingTipsTranslationRepository::class)]
#[ORM\Table(name: 'cooking_tips_translation')]
#[ORM\UniqueConstraint(name: 'uniq_cooking_tips_locale', columns: ['cooking_tips_id', 'locale'])]
#[ORM\Index(name: 'idx_cooking_tips_translation_locale', columns: ['locale'])]
class CookingTipsTranslation implements TranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CookingTips::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'cooking_tips_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?CookingTips $cookingTip = null;

    #[ORM\Column(type: 'string', length: 5)]
    private string $locale = 'fr';

    #[ORM\Column(type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $reviewed = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $cookingStep = null;

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

    public function getCookingTip(): ?CookingTips
    {
        return $this->cookingTip;
    }

    public function setCookingTip(?CookingTips $cookingTip): static
    {
        $this->cookingTip = $cookingTip;

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

    public function getCookingStep(): ?string
    {
        return $this->cookingStep;
    }

    public function setCookingStep(?string $cookingStep): static
    {
        $this->cookingStep = $cookingStep;

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
