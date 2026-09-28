<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Spices;
use App\Entity\SpicyMatchHistory;

class SpicyMatchHistoryService
{
    /**
     * @param iterable<SpicyMatchHistory> $histories
     * @return array<int, Spices>
     */
    public function getSpicesFromHistories(iterable $histories): array
    {
        $spices = [];
        /** @var SpicyMatchHistory $history */
        foreach ($histories as $history) {
            foreach ($history->getSpicyMatch()->getSpices() as $spice) {
                $spices[$spice->getId()] = $spice;
            }
        }

        return $spices;
    }
}
