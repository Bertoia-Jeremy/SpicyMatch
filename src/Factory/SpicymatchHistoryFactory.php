<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\SpicyMatchHistory;

class SpicyMatchHistoryFactory
{
    public function create(): SpicyMatchHistory
    {
        $spicyMatchHistory = new SpicyMatchHistory();
        $spicyMatchHistory->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime())
            ->setFavorite(false)
        ;

        return $spicyMatchHistory;
    }
}
