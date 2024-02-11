<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Repository\AromaticCompoundRepository;
use App\Repository\SpicesRepository;
use App\Service\SpiceMatchmaker;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class Spicymatch
{
    use DefaultActionTrait;
    
    #[LiveProp(writable: true)]
    public array $spices = [];

    public function __construct(
        private SpicesRepository $spicesRepository,
        private AromaticCompoundRepository $aromaticCompoundRepository,
        private SpiceMatchmaker $spiceMatchmaker
    )
    {
        $this->spices = [
            "selectedSpices" => [],
            "compatibleSpices" => $spicesRepository->findAllSpices()
        ];
    }

    public function getResults(): array
    {
       // $selectedSpices = $this->spicesRepository->search($this->spices);
       // $compatibleSpices = $this->spicesRepository->search($this->selectedSpices);


        if (count($this->spices["selectedSpices"]) === 0) {
            $compatibleSpices = $this->spicesRepository->findAllSpices();
        } else {
            $compatibleSpices = $this->spiceMatchmaker->getSpicesCompatible($this->spices["selectedSpices"]);

            if(!$compatibleSpices){
                $compatibleSpices = $this->spices['compatibleSpices'];
            }
        }
    
        return [
            "selectedSpices" => $this->spices["selectedSpices"],
            "compatibleSpices" => $compatibleSpices
        ];
    }
}