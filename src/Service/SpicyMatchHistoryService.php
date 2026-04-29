<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Spices;
use App\Entity\SpicyMatchHistory;

/**
 * Utility service for SpicyMatchHistory.
 * Spices are accessed directly via $history->getSpicyMatch()->getSpices().
 */
class SpicyMatchHistoryService
{
    /**
     * Returns all spices across a collection of SpicyMatchHistory, keyed by spice ID.
     *
     * @param iterable<SpicyMatchHistory> $histories
     *
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
