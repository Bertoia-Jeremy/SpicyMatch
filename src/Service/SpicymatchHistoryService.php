<?php

namespace App\Service;

use App\Repository\SpicesRepository;
use App\Entity\SpicymatchHistory;

class SpicymatchHistoryService
{
    public function __construct(
        private SpicesRepository $spicesRepository
    ) {
    }

    public function getSpicesFromHistories($histories): array
    {
        $spicesHistoriesString = '';

        foreach ($histories as $history) {
            /** @var SpicymatchHistory $history */
            $spicesHistoriesString .= $history->getSpicesIds() . ',';
        }

        $spicesHistoriesString = trim($spicesHistoriesString, ',');

        return $this->getSpicesFromHistory($spicesHistoriesString);
    }

    public function getSpicesFromHistory(string $spicesHistoriesString): array
    {
        $spicesArray = $this->spicesRepository->findSpicesForMatch($spicesHistoriesString);

        $spices = [];
        foreach ($spicesArray as $spice) {
            $spices[$spice['id']] = $spice;
        }

        return $spices;
    }
}
