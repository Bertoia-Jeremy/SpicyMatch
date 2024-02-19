<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\SpicymatchHistory;

class SpicymatchHistoryFactory
{
    public function create(): SpicymatchHistory
    {
        $spicymatchHistory = new SpicymatchHistory();
        $spicymatchHistory->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime())
            ->setIsFavorite(false)
        ;

        return $spicymatchHistory;
    }
}
