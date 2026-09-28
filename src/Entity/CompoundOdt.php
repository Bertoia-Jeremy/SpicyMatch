<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\Repository\CompoundOdtRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompoundOdtRepository::class)]
#[ORM\Table(name: 'compound_odt')]
class CompoundOdt
{
    #[ORM\Column(name: 'confidence', type: 'string', length: 20, enumType: DataConfidence::class, options: [
        'default' => 'placeholder',
    ])]
    private DataConfidence $confidence = DataConfidence::PLACEHOLDER;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: AromaticCompound::class)]
        #[ORM\JoinColumn(name: 'aromatic_compound_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
        private AromaticCompound $aromaticCompound,
        #[ORM\Id]
        #[ORM\Column(name: 'matrix', type: 'string', length: 10, enumType: OdtMatrix::class)]
        private OdtMatrix $matrix,
        #[ORM\Column(name: 'odt_ppm', type: 'decimal', precision: 14, scale: 8)]
        private string $odtPpm,
        #[ORM\Column(name: 'reference_source', type: 'string', length: 255)]
        private string $referenceSource,
    ) {
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
