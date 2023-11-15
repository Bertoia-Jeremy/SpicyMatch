<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Repository\AromaticCompoundRepository;
use App\Repository\SpicesRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class Search
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(
        private SpicesRepository $spicesRepository,
        private AromaticCompoundRepository $aromaticCompoundRepository
    )
    {
    }

    public function getResults(): array
    {
        if($this->query === ""){
            return [];
        }


        $spices = $this->spicesRepository->search($this->query);
        $aromaticsCompounds = $this->aromaticCompoundRepository->search($this->query);

        $results = array_merge($spices, $aromaticsCompounds);
        sort($results, SORT_STRING);
        
        return $results;
    }
}