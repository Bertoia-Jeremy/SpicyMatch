<?php

namespace App\Service;

use App\Repository\SpicesRepository;
use App\Entity\SpicyMatchHistory;

class SpicyMatchHistoryService
{
    public function __construct(
        private SpicesRepository $spicesRepository
    ) {
    }

    public function getSpicesFromHistories($histories): array
    {
        $spicesHistoriesString = '';

        foreach ($histories as $history) {
            /** @var SpicyMatchHistory $history */
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
