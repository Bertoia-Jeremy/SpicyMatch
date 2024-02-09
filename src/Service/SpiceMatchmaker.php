<?php

namespace App\Service;

use App\Repository\SpicesRepository;

class SpiceMatchmaker
{
    public function __construct(
        private SpicesRepository $spicesRepository
    )
    {
    }

    public function getSpicesCompatible(array $spices){
         // Récupération de tous les composés aromatiques
         $allAromaticsCompoundsIds = $this->getAllAromaticsCompounds(
            $spices
        );

        // Filtre et selection des composés aromatiques en commun
        $communAromaticsCompoundsIds = $this->getAromaticsCompoundsInCommon(
            $allAromaticsCompoundsIds,
            count($spices)
        );

        if (count($communAromaticsCompoundsIds['main']) + count(
            $communAromaticsCompoundsIds['secondary']
        ) === 0) {
            return false;
        }

        // Récupération de toutes les épices possédant ces composés aromatiques par ordre d'affinités aux composés
        $spicesIdMatched = $this->spicesRepository->getByMainAromaticsCompounds(
            $communAromaticsCompoundsIds['main'],
            $communAromaticsCompoundsIds['secondary']
        );

        return $this->spicesRepository->findSpicesForMatch($spicesIdMatched, $spices);
    }

    private function getAllAromaticsCompounds(array $spicesId): array
    {
        $mainAromaticsCompoundsIds = [];
        $secondaryAromaticsCompoundsIds = [];

        foreach ($spicesId as $id) {
            /** @var Spices $spice */
            $spice = $this->spicesRepository->findOneBy([
                'id' => (int) $id,
            ]);

            if ($spice !== null) {
                $arrayMainAromaticCompound = $spice->getAromaticsCompounds()->getIterator();
                $mainAromaticsCompoundsIds = $this->getAromaticsCompoundsFromIteratorSpice(
                    $arrayMainAromaticCompound,
                    $mainAromaticsCompoundsIds
                );

                $arraySecondaryAromaticCoumpound = $spice->getSecondaryAromaticsCompounds()->getIterator();
                $secondaryAromaticsCompoundsIds = $this->getAromaticsCompoundsFromIteratorSpice(
                    $arraySecondaryAromaticCoumpound,
                    $secondaryAromaticsCompoundsIds
                );
            }
        }

        return [
            'main' => $mainAromaticsCompoundsIds,
            'secondary' => $secondaryAromaticsCompoundsIds,
        ];
    }

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

    private function getAromaticsCompoundsFromIteratorSpice(
        $iteratorAromaticCompound,
        array $allAromaticsCompoundsIds
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