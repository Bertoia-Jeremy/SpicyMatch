<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslationInterface;
use App\Repository\AchievementTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Traduction localisée d'un succès (Achievement). Une ligne par (succès, locale).
 * slug / icon / enums restent invariants sur Achievement.
 */
#[ORM\Entity(repositoryClass: AchievementTranslationRepository::class)]
#[ORM\Table(name: 'achievement_translation')]
#[ORM\UniqueConstraint(name: 'uniq_achievement_locale', columns: ['achievement_id', 'locale'])]
#[ORM\Index(name: 'idx_achievement_translation_locale', columns: ['locale'])]
class AchievementTranslation implements TranslationInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Achievement::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'achievement_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Achievement $achievement = null;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAchievement(): ?Achievement
    {
        return $this->achievement;
    }

    public function setAchievement(?Achievement $achievement): static
    {
        $this->achievement = $achievement;

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
}
