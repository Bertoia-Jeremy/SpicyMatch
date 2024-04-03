<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\SpicyMatch;
use App\Entity\SpicyMatchHistory;

class SpicyMatchHistoryFactory
{
    public function create(SpicyMatch $spicyMatch): SpicyMatchHistory
    {
        $spicyMatchHistory = new SpicyMatchHistory();
        $spicyMatchHistory->setSpicyMatchId($spicyMatch)
            ->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime())
            ->setFavorite(false)
        ;

        return $spicyMatchHistory;
    }
}
