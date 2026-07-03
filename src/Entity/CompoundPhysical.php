<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AromaKinetics;
use App\Enum\DataConfidence;
use App\Repository\CompoundPhysicalRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Propriétés physico-chimiques (logP, bp, vp). Source : PubChem + NIST WebBook.
 * Champs nullables — données importées progressivement.
 */
#[ORM\Entity(repositoryClass: CompoundPhysicalRepository::class)]
#[ORM\Table(name: 'compound_physical')]
class CompoundPhysical
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: AromaticCompound::class)]
    #[ORM\JoinColumn(name: 'aromatic_compound_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AromaticCompound $compound;

    /**
     * log10(K_ow), plage typique [-2, 8].
     */
    #[ORM\Column(name: 'log_p', type: 'float', nullable: true)]
    private ?float $logP = null;

    /**
     * °C à 1 atm.
     */
    #[ORM\Column(name: 'boiling_point_celsius', type: 'integer', nullable: true)]
    private ?int $boilingPointCelsius = null;

    /**
     * Pa à 25 °C.
     */
    #[ORM\Column(name: 'vapor_pressure_pa', type: 'float', nullable: true)]
    private ?float $vaporPressurePa = null;

    #[ORM\Column(name: 'source', type: 'string', length: 255, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(name: 'confidence', type: 'string', length: 20, enumType: DataConfidence::class, options: [
        'default' => 'placeholder',
    ])]
    private DataConfidence $confidence = DataConfidence::PLACEHOLDER;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(AromaticCompound $compound)
    {
        $this->compound = $compound;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompound(): AromaticCompound
    {
        return $this->compound;
    }

    public function getLogP(): ?float
    {
        return $this->logP;
    }

    public function setLogP(?float $logP): self
    {
        $this->logP = $logP;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getBoilingPointCelsius(): ?int
    {
        return $this->boilingPointCelsius;
    }

    public function setBoilingPointCelsius(?int $boilingPointCelsius): self
    {
        $this->boilingPointCelsius = $boilingPointCelsius;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getVaporPressurePa(): ?float
    {
        return $this->vaporPressurePa;
    }

    public function setVaporPressurePa(?float $vaporPressurePa): self
    {
        $this->vaporPressurePa = $vaporPressurePa;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getConfidence(): DataConfidence
    {
        return $this->confidence;
    }

    public function setConfidence(DataConfidence $confidence): self
    {
        $this->confidence = $confidence;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * K_ow = 10^logP, ou null si logP absent.
     */
    public function octanolWaterPartition(): ?float
    {
        return null === $this->logP ? null : 10 ** $this->logP;
    }

    public function aromaKinetics(): ?AromaKinetics
    {
        return AromaKinetics::fromBoilingPoint($this->boilingPointCelsius);
    }
}
