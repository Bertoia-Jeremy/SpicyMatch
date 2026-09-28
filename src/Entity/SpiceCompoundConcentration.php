<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DataConfidence;
use App\Repository\SpiceCompoundConcentrationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpiceCompoundConcentrationRepository::class)]
#[ORM\Table(name: 'spice_compound_concentration')]
#[ORM\Index(name: 'idx_compound', columns: ['aromatic_compound_id'])]
class SpiceCompoundConcentration
{
    #[ORM\Column(name: 'confidence', type: 'string', length: 20, enumType: DataConfidence::class, options: [
        'default' => 'placeholder',
    ])]
    private DataConfidence $confidence = DataConfidence::PLACEHOLDER;

    #[ORM\Column(name: 'imported_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: Spices::class)]
        #[ORM\JoinColumn(name: 'spice_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
        private Spices $spice,
        #[ORM\Id]
        #[ORM\ManyToOne(targetEntity: AromaticCompound::class)]
        #[ORM\JoinColumn(name: 'aromatic_compound_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
        private AromaticCompound $aromaticCompound,
        #[ORM\Column(name: 'concentration_ppm', type: 'decimal', precision: 14, scale: 4)]
        private string $concentrationPpm,
        #[ORM\Column(name: 'source', type: 'string', length: 255)]
        private string $source,
    ) {
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
