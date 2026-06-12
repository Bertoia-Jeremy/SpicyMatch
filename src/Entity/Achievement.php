<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Translation\TranslatableInterface;
use App\Enum\AchievementRarity;
use App\Enum\AchievementTrigger;
use App\Enum\GameDifficulty;
use App\Enum\GameMode;
use App\Repository\AchievementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catalogue of achievements. Populated via fixtures.
 * trigger_value is the threshold (e.g., 10 for "10 matches done").
 */
#[ORM\Entity(repositoryClass: AchievementRepository::class)]
class Achievement implements TranslatableInterface
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

    /**
     * FontAwesome class or emoji.
     */
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

    /**
     * Used only for EASTER_EGG_FOUND trigger — identifies the specific secret.
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $easterEggSlug = null;

    /**
     * Feature flag to enable/disable an achievement without deleting it.
     * New achievements are seeded disabled and activated after QA.
     */
    #[ORM\Column(options: [
        'default' => true,
    ])]
    private bool $enabled = true;

    /**
     * Optional context: restrict the achievement to a specific game mode.
     * Null = wildcard (any mode counts).
     */
    #[ORM\Column(nullable: true, enumType: GameMode::class)]
    private ?GameMode $contextGameMode = null;

    /**
     * Optional context: restrict the achievement to a specific aromatic group.
     * Null = wildcard (any group counts).
     */
    #[ORM\ManyToOne(targetEntity: AromaticGroups::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?AromaticGroups $contextAromaticGroup = null;

    /**
     * Optional context: restrict the achievement to sessions played at a specific difficulty.
     * Null = wildcard.
     */
    #[ORM\Column(nullable: true, enumType: GameDifficulty::class)]
    private ?GameDifficulty $contextDifficulty = null;

    /**
     * @var Collection<int, AchievementTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'achievement', targetEntity: AchievementTranslation::class, cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, AchievementTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(AchievementTranslation $translation): static
    {
        if (! $this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setAchievement($this);
        }

        return $this;
    }

    public function removeTranslation(AchievementTranslation $translation): static
    {
        if ($this->translations->removeElement($translation) && $translation->getAchievement() === $this) {
            $translation->setAchievement(null);
        }

        return $this;
    }

    public function getTranslation(string $locale): ?AchievementTranslation
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

    public function getLocalizedName(string $locale): string
    {
        return $this->getTranslation($locale)?->getName() ?? $this->name;
    }

    public function getLocalizedDescription(string $locale): string
    {
        return $this->getTranslation($locale)?->getDescription() ?? $this->description;
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

    public function getEasterEggSlug(): ?string
    {
        return $this->easterEggSlug;
    }

    public function setEasterEggSlug(?string $easterEggSlug): static
    {
        $this->easterEggSlug = $easterEggSlug;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getContextGameMode(): ?GameMode
    {
        return $this->contextGameMode;
    }

    public function setContextGameMode(?GameMode $contextGameMode): static
    {
        $this->contextGameMode = $contextGameMode;

        return $this;
    }

    public function getContextAromaticGroup(): ?AromaticGroups
    {
        return $this->contextAromaticGroup;
    }

    public function setContextAromaticGroup(?AromaticGroups $contextAromaticGroup): static
    {
        $this->contextAromaticGroup = $contextAromaticGroup;

        return $this;
    }

    public function getContextDifficulty(): ?GameDifficulty
    {
        return $this->contextDifficulty;
    }

    public function setContextDifficulty(?GameDifficulty $contextDifficulty): static
    {
        $this->contextDifficulty = $contextDifficulty;

        return $this;
    }
}
