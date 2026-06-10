<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompoundPhysical;

interface CompoundPhysicalRepositoryInterface
{
    /**
     * @param int[] $compoundIds
     *
     * @return array<int, CompoundPhysical>
     */
    public function loadByCompoundIds(array $compoundIds): array;
}
