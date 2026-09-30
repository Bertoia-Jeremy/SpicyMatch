<?php

declare(strict_types=1);

namespace App\ValueObject\Recipe;

use App\Entity\AromaticGroups;

final readonly class RecipeFamily
{
    public function __construct(
        public AromaticGroups $group,
        public int $count,
    ) {
    }
}
