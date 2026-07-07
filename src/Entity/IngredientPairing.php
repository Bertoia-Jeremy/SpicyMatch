<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FlavorGraphAffinityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FlavorGraphAffinityRepository::class)]
#[ORM\Table(name: 'ingredient_pairing')]
#[ORM\Index(columns: ['spice_a_id', 'affinity_score', 'spice_b_id'], name: 'idx_topn')]
class IngredientPairing
{
    #[ORM\Id]
    #[ORM\Column(name: 'spice_a_id', type: 'integer')]
    private int $spiceAId;

    #[ORM\Id]
    #[ORM\Column(name: 'spice_b_id', type: 'integer')]
    private int $spiceBId;

    #[ORM\Column(name: 'affinity_score', type: 'float')]
    private float $affinityScore;

    public function __construct(int $spiceAId, int $spiceBId, float $affinityScore)
    {
        $this->spiceAId = $spiceAId;
        $this->spiceBId = $spiceBId;
        $this->affinityScore = $affinityScore;
    }

    public function getSpiceAId(): int
    {
        return $this->spiceAId;
    }

    public function getSpiceBId(): int
    {
        return $this->spiceBId;
    }

    public function getAffinityScore(): float
    {
        return $this->affinityScore;
    }
}
