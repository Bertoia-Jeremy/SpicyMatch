<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\SpicyMatch;

class SpicyMatchFactory
{
    public function create(): SpicyMatch
    {
        $spicymatch = new SpicyMatch();
        $spicymatch->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime())
        ;

        return $spicymatch;
    }
}
