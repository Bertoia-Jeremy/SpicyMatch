<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OdtMatrix;
use App\Repository\SpiceActiveCompoundRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpiceActiveCompoundRepository::class)]
#[ORM\Table(name: 'spice_active_compound')]
#[ORM\Index(name: 'idx_spice_matrix', columns: ['spice_id', 'matrix'])]
#[ORM\Index(name: 'idx_compound_spice', columns: ['aromatic_compound_id', 'spice_id', 'matrix'])]
#[ORM\Index(name: 'idx_spice_cover', columns: ['spice_id', 'matrix', 'aromatic_compound_id', 'oav_value'])]
class SpiceActiveCompound
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'spice_id', type: 'integer')]
        private int $spiceId,
        #[ORM\Id]
        #[ORM\Column(name: 'aromatic_compound_id', type: 'integer')]
        private int $aromaticCompoundId,
        #[ORM\Column(name: 'oav_value', type: 'float')]
        private float $oavValue,
        #[ORM\Id]
        #[ORM\Column(name: 'matrix', type: 'string', length: 5, enumType: OdtMatrix::class, options: [
            'default' => 'air',
        ])]
        private OdtMatrix $matrix = OdtMatrix::AIR
    ) {
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
