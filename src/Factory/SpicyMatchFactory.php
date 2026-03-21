<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\SpicyMatch;

class SpicyMatchFactory
{
    public function create(): SpicyMatch
    {
        // Constructor initializes createdAt and updatedAt as DateTimeImmutable
        return new SpicyMatch();
    }
}
