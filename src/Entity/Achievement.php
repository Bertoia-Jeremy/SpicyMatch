<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Repository\AchievementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catalogue of achievements. Populated via fixtures.
 * trigger_value is the threshold (e.g., 10 for "10 matches done").
 */
#[ORM\Entity(repositoryClass: AchievementRepository::class)]
class Achievement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    /** FontAwesome class or emoji */
    #[ORM\Column(length: 100)]
    private string $icon = 'fa-star';

    #[ORM\Column(name: 'trigger_type', enumType: AchievementTrigger::class)]
    private AchievementTrigger $trigger;

    #[ORM\Column]
    private int $triggerValue = 1;

    #[ORM\Column]
    private int $xpReward = 10;

    #[ORM\Column(enumType: AchievementRarity::class)]
    private AchievementRarity $rarity = AchievementRarity::COMMON;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getTrigger(): AchievementTrigger
    {
        return $this->trigger;
    }

    public function setTrigger(AchievementTrigger $trigger): static
    {
        $this->trigger = $trigger;

        return $this;
    }

    public function getTriggerValue(): int
    {
        return $this->triggerValue;
    }

    public function setTriggerValue(int $triggerValue): static
    {
        $this->triggerValue = $triggerValue;

        return $this;
    }

    public function getXpReward(): int
    {
        return $this->xpReward;
    }

    public function setXpReward(int $xpReward): static
    {
        $this->xpReward = $xpReward;

        return $this;
    }

    public function getRarity(): AchievementRarity
    {
        return $this->rarity;
    }

    public function setRarity(AchievementRarity $rarity): static
    {
        $this->rarity = $rarity;

        return $this;
    }
}
