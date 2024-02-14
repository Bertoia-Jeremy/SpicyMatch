<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\SpicymatchHistory;
use App\Repository\AromaticCompoundRepository;
use App\Repository\SpicesRepository;
use App\Service\SpiceMatchmaker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[AsLiveComponent]
class Spicymatch extends AbstractController
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
        if (!empty($this->spices["selectedSpices"])) {
            $idsString = $this->spiceMatchmaker->arrayToString($this->spices['selectedSpices']);

            $selectedSpices = $this->spicesRepository->findSpicesForMatch($idsString);

            foreach($selectedSpices as $spice){
                $groupByAromaticGroup[$spice['groupName']][] = $spice;
            }
            
            $sharedAromaticsCompounds =  $this->spiceMatchmaker->getAllSharedAromaticsCompounds($this->spices["selectedSpices"]);
            if($sharedAromaticsCompounds){
                // Récupération de tous les ids des épices possédant ces composés aromatiques par ordre d'affinités aux composés
                $idsCompatibleSpices = $this->spicesRepository->getByAromaticsCompounds(
                    $sharedAromaticsCompounds['main'],
                    $sharedAromaticsCompounds['secondary']
                );
                
                $idsWithoutSelectedSpices = array_diff( $idsCompatibleSpices, $this->spices['selectedSpices'] );
                $idsStringCompatibleSpices = implode(",", $idsWithoutSelectedSpices);

                if($idsStringCompatibleSpices === ""){
                    $compatibleSpices = false;  
                }else{
                    $compatibleSpices = $this->spicesRepository->findSpicesForMatch($idsStringCompatibleSpices);  
                }
            }
        }
        
        if(!isset($compatibleSpices)){
            $compatibleSpices = $this->spices['compatibleSpices'];
        }

        return [
            "selectedSpices" => $groupByAromaticGroup ?? $this->spices["selectedSpices"],
            "compatibleSpices" => $compatibleSpices
        ];
    }

    #[LiveAction]
    public function nextStep(EntityManagerInterface $entityManager){
        $spicymatchHistory = new SpicymatchHistory();

        $spicymatchHistory->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime())
            ->setUserId($this->getUser())
            ->setNbSpice(count($this->spices['selectedSpices']))
            ->setSpicesIds($this->spiceMatchmaker->arrayToString($this->spices['selectedSpices']));
        
        $entityManager->persist($spicymatchHistory);
        $entityManager->flush();

        return $this->redirectToRoute('view_spicy_match', [
            "id" => $spicymatchHistory->getId()
        ]); 
    }
}