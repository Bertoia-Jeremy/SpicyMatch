<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DiscoveryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Records the first user to discover a unique spice combination.
 * combinationHash = md5(implode(',', sorted spice IDs)) for fast uniqueness check.
 */
#[ORM\Entity(repositoryClass: DiscoveryRepository::class)]
#[ORM\UniqueConstraint(fields: ['combinationHash'])]
class Discovery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $combinationHash = '';

    /**
     * @var Collection<int, Spices>
     */
    #[ORM\ManyToMany(targetEntity: Spices::class)]
    #[ORM\JoinTable(name: 'discovery_spices')]
    private Collection $spices;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Users $discoveredBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $discoveredAt;

    public function __construct()
    {
        $this->spices = new ArrayCollection();
        $this->discoveredAt = new \DateTimeImmutable();
    }

    /**
     * @param list<int> $spiceIds
     */
    public static function buildHash(array $spiceIds): string
    {
        sort($spiceIds);

        return md5(implode(',', $spiceIds));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCombinationHash(): string
    {
        return $this->combinationHash;
    }

    public function setCombinationHash(string $hash): static
    {
        $this->combinationHash = $hash;

        return $this;
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

    public function getDiscoveredBy(): ?Users
    {
        return $this->discoveredBy;
    }

    public function setDiscoveredBy(?Users $user): static
    {
        $this->discoveredBy = $user;

        return $this;
    }

    public function getDiscoveredAt(): \DateTimeImmutable
    {
        return $this->discoveredAt;
    }
}
