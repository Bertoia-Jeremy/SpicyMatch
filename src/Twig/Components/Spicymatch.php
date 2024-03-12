<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Factory\SpicyMatchFactory;
use App\Repository\SpicesRepository;
use App\Service\SpiceMatchmakerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class SpicyMatch extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public array $spices = [];

    public function __construct(
        private SpicesRepository $spicesRepository,
        private SpiceMatchmakerService $spiceMatchmakerService
    ) {
        $this->spices = [
            'selectedSpices' => [],
            'compatibleSpices' => $spicesRepository->findAllSpices(),
        ];
    }

    public function getResults(): array
    {
        if (! empty($this->spices['selectedSpices'])) {
            $idsString = $this->spiceMatchmakerService->arrayToString($this->spices['selectedSpices']);

            $selectedSpices = $this->spicesRepository->findSpicesForMatch($idsString);

            foreach ($selectedSpices as $spice) {
                $groupByAromaticGroup[$spice['groupName']][] = $spice;
            }

            $sharedAromaticsCompounds = $this->spiceMatchmakerService->getAllSharedAromaticsCompounds(
                $this->spices['selectedSpices']
            );
            if ($sharedAromaticsCompounds) {
                // Récupération de tous les ids des épices possédant ces composés aromatiques par ordre d'affinités aux composés
                $idsCompatibleSpices = $this->spicesRepository->getByAromaticsCompounds(
                    $sharedAromaticsCompounds['main'],
                    $sharedAromaticsCompounds['secondary']
                );

                $idsWithoutSelectedSpices = array_diff($idsCompatibleSpices, $this->spices['selectedSpices']);
                $idsStringCompatibleSpices = implode(',', $idsWithoutSelectedSpices);

                if ($idsStringCompatibleSpices === '') {
                    $compatibleSpices = false;
                } else {
                    $compatibleSpices = $this->spicesRepository->findSpicesForMatch($idsStringCompatibleSpices);
                }
            }
        }

        if (! isset($compatibleSpices)) {
            $compatibleSpices = $this->spices['compatibleSpices'];
        }

        return [
            'selectedSpices' => $groupByAromaticGroup ?? $this->spices['selectedSpices'],
            'compatibleSpices' => $compatibleSpices,
        ];
    }

    #[LiveAction]
    public function nextStep(
        EntityManagerInterface $entityManager,
        SpicyMatchFactory $spicyMatchFactory
    ): \Symfony\Component\HttpFoundation\RedirectResponse {
        $spicyMatch = $spicyMatchFactory->create();

        $spicyMatch->setUserId($this->getUser())
            ->setNbSpice(count($this->spices['selectedSpices']))
            ->setSpicesIds($this->spiceMatchmakerService->arrayToString($this->spices['selectedSpices']));

        $entityManager->persist($spicyMatch);
        $entityManager->flush();

        return $this->redirectToRoute('view_spicy_match', [
            'id' => $spicyMatch->getId(),
        ]);
    }
}
