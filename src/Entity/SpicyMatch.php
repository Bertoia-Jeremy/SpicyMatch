<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OdtMatrix;
use App\Repository\SpicyMatchRepository;
use App\ValueObject\Match\CulinaryContext;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpicyMatchRepository::class)]
class SpicyMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'spicyMatches')]
    #[ORM\JoinColumn(nullable: false, name: 'user_id')]
    private ?Users $user = null;

    /**
     * @var Collection<int, Spices>
     */
    #[ORM\ManyToMany(targetEntity: Spices::class)]
    #[ORM\JoinTable(name: 'spicy_match_spices')]
    private Collection $spices;

    /**
     * @var Collection<int, SpicyMatchResult>
     */
    #[ORM\OneToMany(mappedBy: 'spicyMatch', targetEntity: SpicyMatchResult::class, cascade: [
        'persist',
        'remove',
    ], orphanRemoval: true)]
    private Collection $results;

    /**
     * @var Collection<int, SpicyMatchHistory>
     */
    #[ORM\OneToMany(mappedBy: 'spicyMatch', targetEntity: SpicyMatchHistory::class, cascade: [
        'remove',
    ], orphanRemoval: true)]
    private Collection $spicyMatchHistories;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(options: [
        'default' => false,
    ])]
    private bool $isManual = false;

    /**
     * Contexte culinaire au moment de la composition.
     * Permet de restituer le ranking exact lors de l'affichage historique.
     */
    #[ORM\Column(name: 'matrix', type: 'string', length: 5, enumType: OdtMatrix::class, options: [
        'default' => 'air',
    ])]
    private OdtMatrix $matrix = OdtMatrix::AIR;

    #[ORM\Column(name: 'fat_ratio', type: 'float', options: [
        'default' => 0.0,
    ])]
    private float $fatRatio = 0.0;

    /**
     * waterRatio persisté explicitement (et non dérivé) pour garantir l'invariant
     * fat + water ≈ 1 à long terme, indépendamment d'une éventuelle migration ou
     * d'un patch qui casserait la dérivation.
     */
    #[ORM\Column(name: 'water_ratio', type: 'float', options: [
        'default' => 1.0,
    ])]
    private float $waterRatio = 1.0;

    #[ORM\Column(name: 'cooking_time_min', type: 'integer', options: [
        'default' => 0,
    ])]
    private int $cookingTimeMin = 0;

    #[ORM\Column(name: 'temperature_celsius', type: 'integer', options: [
        'default' => 20,
    ])]
    private int $temperatureCelsius = 20;

    public function __construct()
    {
        $this->spices = new ArrayCollection();
        $this->results = new ArrayCollection();
        $this->spicyMatchHistories = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function setUser(?Users $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @deprecated Use getUser()
     */
    public function getUserId(): ?Users
    {
        return $this->user;
    }

    /**
     * @deprecated Use setUser()
     */
    public function setUserId(?Users $user): static
    {
        return $this->setUser($user);
    }

    /**
     * @return Collection<int, Spices>
     */
    public function getSpices(): Collection
    {
        return $this->spices;
    }

    public function addSpice(Spices $spice): static
    {
        if (! $this->spices->contains($spice)) {
            $this->spices->add($spice);
        }

        return $this;
    }

    public function removeSpice(Spices $spice): static
    {
        $this->spices->removeElement($spice);

        return $this;
    }

    public function getSpiceCount(): int
    {
        return $this->spices->count();
    }

    /**
     * @return Collection<int, SpicyMatchResult>
     */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function addResult(SpicyMatchResult $result): static
    {
        if (! $this->results->contains($result)) {
            $this->results->add($result);
            $result->setSpicyMatch($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, SpicyMatchHistory>
     */
    public function getSpicyMatchHistories(): Collection
    {
        return $this->spicyMatchHistories;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function isManual(): bool
    {
        return $this->isManual;
    }

    public function setIsManual(bool $isManual): static
    {
        $this->isManual = $isManual;

        return $this;
    }

    public function getMatrix(): OdtMatrix
    {
        return $this->matrix;
    }

    public function setMatrix(OdtMatrix $matrix): static
    {
        $this->matrix = $matrix;

        return $this;
    }

    public function getFatRatio(): float
    {
        return $this->fatRatio;
    }

    public function setFatRatio(float $fatRatio): static
    {
        $this->fatRatio = $fatRatio;
        // Maintient l'invariant fat + water ≈ 1 même si setFatRatio est appelé seul.
        $this->waterRatio = max(0.0, min(1.0, 1.0 - $fatRatio));

        return $this;
    }

    public function getWaterRatio(): float
    {
        return $this->waterRatio;
    }

    public function setWaterRatio(float $waterRatio): static
    {
        $this->waterRatio = $waterRatio;

        return $this;
    }

    public function getCookingTimeMin(): int
    {
        return $this->cookingTimeMin;
    }

    public function setCookingTimeMin(int $cookingTimeMin): static
    {
        $this->cookingTimeMin = $cookingTimeMin;

        return $this;
    }

    public function getTemperatureCelsius(): int
    {
        return $this->temperatureCelsius;
    }

    public function setTemperatureCelsius(int $temperatureCelsius): static
    {
        $this->temperatureCelsius = $temperatureCelsius;

        return $this;
    }

    /**
     * Reconstruit le contexte culinaire complet depuis les colonnes persistées.
     */
    public function getCulinaryContext(): CulinaryContext
    {
        return new CulinaryContext(
            $this->matrix,
            $this->fatRatio,
            $this->getWaterRatio(),
            $this->cookingTimeMin,
            $this->temperatureCelsius,
        );
    }

    /**
     * Persiste le contexte culinaire dans les colonnes dédiées.
     */
    public function setCulinaryContext(CulinaryContext $context): static
    {
        $this->matrix = $context->matrix;
        $this->fatRatio = $context->fatRatio;
        $this->waterRatio = $context->waterRatio;
        $this->cookingTimeMin = $context->cookingTimeMin;
        $this->temperatureCelsius = $context->temperatureCelsius;

        return $this;
    }
}
