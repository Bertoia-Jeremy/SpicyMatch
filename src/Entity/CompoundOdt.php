<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OdtMatrix;
use App\Repository\CompoundOdtRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Seuil olfactif (Odor Detection Threshold) d'un composé aromatique.
 *
 * PK composite (aromatic_compound_id, matrix) : un composé peut avoir des ODT
 * différents selon la matrice (air / eau / huile). Source de référence : van Gemert (2011).
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §5.1
 */
#[ORM\Entity(repositoryClass: CompoundOdtRepository::class)]
#[ORM\Table(name: 'compound_odt')]
class CompoundOdt
{
    /**
     * Partie 1 de la PK composite : composé aromatique.
     */
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: AromaticCompound::class)]
    #[ORM\JoinColumn(name: 'aromatic_compound_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AromaticCompound $aromaticCompound;

    /**
     * Partie 2 de la PK composite : matrice de mesure.
     */
    #[ORM\Id]
    #[ORM\Column(name: 'matrix', type: 'string', length: 10, enumType: OdtMatrix::class)]
    private OdtMatrix $matrix;

    /**
     * Seuil de détection olfactive en ppm (parties par million).
     * Exemple : eugenol/air = 0.0001 ppm.
     */
    #[ORM\Column(name: 'odt_ppm', type: 'decimal', precision: 14, scale: 8)]
    private string $odtPpm;

    /**
     * Source documentaire (ex: "van Gemert (2011) p.78").
     */
    #[ORM\Column(name: 'reference_source', type: 'string', length: 255)]
    private string $referenceSource;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    public function __construct(
        AromaticCompound $aromaticCompound,
        OdtMatrix $matrix,
        string $odtPpm,
        string $referenceSource,
    ) {
        $this->aromaticCompound = $aromaticCompound;
        $this->matrix = $matrix;
        $this->odtPpm = $odtPpm;
        $this->referenceSource = $referenceSource;
        $this->importedAt = new \DateTimeImmutable();
    }

    public function getAromaticCompound(): AromaticCompound
    {
        return $this->aromaticCompound;
    }

    public function getMatrix(): OdtMatrix
    {
        return $this->matrix;
    }

    public function getOdtPpm(): float
    {
        return (float) $this->odtPpm;
    }

    public function setOdtPpm(string $odtPpm): self
    {
        $this->odtPpm = $odtPpm;

        return $this;
    }

    public function getReferenceSource(): string
    {
        return $this->referenceSource;
    }

    public function setReferenceSource(string $referenceSource): self
    {
        $this->referenceSource = $referenceSource;

        return $this;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }
}
