<?php

namespace App\Service;

use App\Repository\SpicesRepository;

class SpiceMatchmakerService
{
    public function __construct(
        private SpicesRepository $spicesRepository,
    ) {
    }

    /**
     * @param array<int> $ids
     */
    public function arrayToString(array $ids): string
    {
        $idsString = '';

        foreach ($ids as $id) {
            $idsString .= ((int) $id) . ',';
        }

        return trim($idsString, ',');
    }

    /**
     * @param list<int> $spices
     *
     * @return false|array{main: list<int>, secondary: list<int>}
     */
    public function getAllSharedAromaticsCompounds(array $spices): false|array
    {
        // Récupération de tous les composés aromatiques
        $allAromaticsCompoundsIds = $this->getAllAromaticsCompounds($spices);

        // Filtre et selection des composés aromatiques en commun
        $sharedAromaticsCompoundsIds = $this->getAromaticsCompoundsInCommon(
            $allAromaticsCompoundsIds,
            count($spices)
        );

        if ((count($sharedAromaticsCompoundsIds['main']) +
            count($sharedAromaticsCompoundsIds['secondary'])) === 0) {
            return false;
        }

        return $sharedAromaticsCompoundsIds;
    }

    /**
     * @param list<int> $spicesId
     *
     * @return array{main: array<int, int>, secondary: array<int, int>}
     */
    private function getAllAromaticsCompounds(array $spicesId): array
    {
        $mainAromaticsCompoundsIds = [];
        $secondaryAromaticsCompoundsIds = [];

        // Single query for all spices instead of N queries
        $ids = array_map(static fn ($id): int => (int) $id, $spicesId);
        $spicesList = $this->spicesRepository->findBy([
            'id' => $ids,
        ]);

        foreach ($spicesList as $spice) {
            $mainAromaticsCompoundsIds = $this->getAromaticsCompoundsFromIteratorSpice(
                $spice->getAromaticsCompounds()
                    ->getIterator(),
                $mainAromaticsCompoundsIds
            );

            $secondaryAromaticsCompoundsIds = $this->getAromaticsCompoundsFromIteratorSpice(
                $spice->getSecondaryAromaticsCompounds()
                    ->getIterator(),
                $secondaryAromaticsCompoundsIds
            );
        }

        return [
            'main' => $mainAromaticsCompoundsIds,
            'secondary' => $secondaryAromaticsCompoundsIds,
        ];
    }

    /**
     * @param array{main: array<int, int>, secondary: array<int, int>} $allAromaticsCompoundsIds
     *
     * @return array{main: list<int>, secondary: list<int>}
     */
    private function getAromaticsCompoundsInCommon(array $allAromaticsCompoundsIds, int $numberSpices): array
    {
        $mainCompounds = $allAromaticsCompoundsIds['main'];
        $secondaryCompounds = $allAromaticsCompoundsIds['secondary'];
        $mainCommon = $secondaryCommon = [];

        foreach ($mainCompounds as $id => $numberMatchMain) {
            $numberMatch = $numberMatchMain + ($secondaryCompounds[$id] ?? 0);

            if ($numberMatch === $numberSpices) {
                $mainCommon[] = $id;
            }
        }

        foreach ($secondaryCompounds as $id => $numberMatchSecondary) {
            $numberMatch = $numberMatchSecondary + ($mainCompounds[$id] ?? 0);

            if (($numberMatch === $numberSpices) && ! isset($mainCommon[$id])) {
                $secondaryCommon[] = $id;
            }
        }

        return [
            'main' => $mainCommon,
            'secondary' => $secondaryCommon,
        ];
    }

    /**
     * @param \Traversable<\App\Entity\AromaticCompound> $iteratorAromaticCompound
     * @param array<int, int>                            $allAromaticsCompoundsIds
     *
     * @return array<int, int>
     */
    private function getAromaticsCompoundsFromIteratorSpice(
        \Traversable $iteratorAromaticCompound,
        array $allAromaticsCompoundsIds,
    ): array {
        foreach ($iteratorAromaticCompound as $aromaticCompound) {
            $aromaticCompoundId = $aromaticCompound->getId();

            if (! array_key_exists($aromaticCompoundId, $allAromaticsCompoundsIds)) {
                $allAromaticsCompoundsIds[$aromaticCompoundId] = 1;
            } else {
                ++$allAromaticsCompoundsIds[$aromaticCompoundId];
            }
        }

        return $allAromaticsCompoundsIds;
    }
}
