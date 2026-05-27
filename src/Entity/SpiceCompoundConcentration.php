<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DataConfidence;
use App\Repository\SpiceCompoundConcentrationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Concentration (en ppm) d'un composé aromatique dans une épice.
 *
 * PK composite (spice_id, aromatic_compound_id).
 * Source typique : FlavorDB, GC-MS.
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §5.2
 */
#[ORM\Entity(repositoryClass: SpiceCompoundConcentrationRepository::class)]
#[ORM\Table(name: 'spice_compound_concentration')]
#[ORM\Index(columns: ['aromatic_compound_id'], name: 'idx_compound')]
class SpiceCompoundConcentration
{
    /**
     * Partie 1 de la PK composite : épice.
     */
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Spices::class)]
    #[ORM\JoinColumn(name: 'spice_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Spices $spice;

    /**
     * Partie 2 de la PK composite : composé aromatique.
     */
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: AromaticCompound::class)]
    #[ORM\JoinColumn(name: 'aromatic_compound_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AromaticCompound $aromaticCompound;

    /**
     * Concentration en parties par million (ppm).
     * Exemple : eugenol dans clou de girofle ≈ 850 000 ppm (85 % de l'huile essentielle).
     */
    #[ORM\Column(name: 'concentration_ppm', type: 'decimal', precision: 14, scale: 4)]
    private string $concentrationPpm;

    /**
     * Traçabilité de la source (ex: "FlavorDB ingredient_id=42").
     */
    #[ORM\Column(name: 'source', type: 'string', length: 255)]
    private string $source;

    /**
     * Niveau de confiance de la concentration (Levier 2). Défaut PLACEHOLDER.
     */
    #[ORM\Column(name: 'confidence', type: 'string', length: 20, enumType: DataConfidence::class, options: [
        'default' => 'placeholder',
    ])]
    private DataConfidence $confidence = DataConfidence::PLACEHOLDER;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    public function __construct(
        Spices $spice,
        AromaticCompound $aromaticCompound,
        string $concentrationPpm,
        string $source,
    ) {
        $this->spice = $spice;
        $this->aromaticCompound = $aromaticCompound;
        $this->concentrationPpm = $concentrationPpm;
        $this->source = $source;
        $this->importedAt = new \DateTimeImmutable();
    }

    public function getSpice(): Spices
    {
        return $this->spice;
    }

    public function getAromaticCompound(): AromaticCompound
    {
        return $this->aromaticCompound;
    }

    public function getConcentrationPpm(): float
    {
        return (float) $this->concentrationPpm;
    }

    public function setConcentrationPpm(string $concentrationPpm): self
    {
        $this->concentrationPpm = $concentrationPpm;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function getConfidence(): DataConfidence
    {
        return $this->confidence;
    }

    public function setConfidence(DataConfidence $confidence): self
    {
        $this->confidence = $confidence;

        return $this;
    }
}
