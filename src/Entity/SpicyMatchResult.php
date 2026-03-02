<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpicyMatchResultRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stores a compatible spice suggestion with its compatibility score for a given SpicyMatch session.
 * Score breakdown: main compounds ×3, secondary ×1, shared AlchemyFlavors ×5, same group bonus +10.
 */
#[ORM\Entity(repositoryClass: SpicyMatchResultRepository::class)]
class SpicyMatchResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SpicyMatch $spicyMatch = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Spices $spice = null;

    /** Normalized score 0–100 */
    #[ORM\Column]
    private int $score = 0;

    #[ORM\Column]
    private int $mainCompoundsCount = 0;

    #[ORM\Column]
    private int $secondaryCompoundsCount = 0;

    #[ORM\Column]
    private int $alchemyFlavorsCount = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSpicyMatch(): ?SpicyMatch
    {
        return $this->spicyMatch;
    }

    public function setSpicyMatch(?SpicyMatch $spicyMatch): static
    {
        $this->spicyMatch = $spicyMatch;

        return $this;
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

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = max(0, min(100, $score));

        return $this;
    }

    public function getMainCompoundsCount(): int
    {
        return $this->mainCompoundsCount;
    }

    public function setMainCompoundsCount(int $count): static
    {
        $this->mainCompoundsCount = $count;

        return $this;
    }

    public function getSecondaryCompoundsCount(): int
    {
        return $this->secondaryCompoundsCount;
    }

    public function setSecondaryCompoundsCount(int $count): static
    {
        $this->secondaryCompoundsCount = $count;

        return $this;
    }

    public function getAlchemyFlavorsCount(): int
    {
        return $this->alchemyFlavorsCount;
    }

    public function setAlchemyFlavorsCount(int $count): static
    {
        $this->alchemyFlavorsCount = $count;

        return $this;
    }
}
