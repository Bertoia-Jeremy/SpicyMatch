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
        // Constructor initializes dates as DateTimeImmutable and favorite defaults to false
        $spicyMatchHistory->setSpicyMatch($spicyMatch);

        return $spicyMatchHistory;
    }
}
