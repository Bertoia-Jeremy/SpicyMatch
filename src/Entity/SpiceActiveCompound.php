<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OdtMatrix;
use App\Repository\SpiceActiveCompoundRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpiceActiveCompoundRepository::class)]
#[ORM\Table(name: 'spice_active_compound')]
#[ORM\Index(columns: ['spice_id', 'matrix'], name: 'idx_spice_matrix')]
#[ORM\Index(columns: ['aromatic_compound_id', 'spice_id', 'matrix'], name: 'idx_compound_spice')]
#[ORM\Index(columns: ['spice_id', 'matrix', 'aromatic_compound_id', 'oav_value'], name: 'idx_spice_cover')]
class SpiceActiveCompound
{
    #[ORM\Id]
    #[ORM\Column(name: 'spice_id', type: 'integer')]
    private int $spiceId;

    #[ORM\Id]
    #[ORM\Column(name: 'aromatic_compound_id', type: 'integer')]
    private int $aromaticCompoundId;

    #[ORM\Id]
    #[ORM\Column(name: 'matrix', type: 'string', length: 5, enumType: OdtMatrix::class, options: [
        'default' => 'air',
    ])]
    private OdtMatrix $matrix;

    #[ORM\Column(name: 'oav_value', type: 'float')]
    private float $oavValue;

    public function __construct(
        int $spiceId,
        int $aromaticCompoundId,
        float $oavValue,
        OdtMatrix $matrix = OdtMatrix::AIR,
    ) {
        $this->spiceId = $spiceId;
        $this->aromaticCompoundId = $aromaticCompoundId;
        $this->oavValue = $oavValue;
        $this->matrix = $matrix;
    }

    public function getSpiceId(): int
    {
        return $this->spiceId;
    }

    public function getAromaticCompoundId(): int
    {
        return $this->aromaticCompoundId;
    }

    public function getMatrix(): OdtMatrix
    {
        return $this->matrix;
    }

    public function getOavValue(): float
    {
        return $this->oavValue;
    }
}
