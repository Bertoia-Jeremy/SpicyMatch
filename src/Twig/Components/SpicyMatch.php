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
    #[LiveProp(writable: true)]
    public array $spices;

    #[LiveProp(writable: true)]
    public ?string $selectedAromaticGroup = null;

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
        $compatibleSpices = $this->spices['compatibleSpices'];
        $groupByAromaticGroup = [];

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
                $idsCompatibleSpices = $this->spicesRepository->getByAromaticsCompounds(
                    $sharedAromaticsCompounds['main'],
                    $sharedAromaticsCompounds['secondary']
                );

                $idsWithoutSelectedSpices = array_diff($idsCompatibleSpices, $this->spices['selectedSpices']);
                $idsStringCompatibleSpices = implode(',', $idsWithoutSelectedSpices);

                if ($idsStringCompatibleSpices === '') {
                    $compatibleSpices = [];
                } else {
                    $compatibleSpices = $this->spicesRepository->findSpicesForMatch($idsStringCompatibleSpices);
                }
            }

            // Get aromatic groups from selected spices
            $selectedAromaticGroups = [];
            foreach ($selectedSpices as $spice) {
                $selectedAromaticGroups[] = $spice['groupName'];
            }
            $selectedAromaticGroups = array_unique($selectedAromaticGroups);

            // Filter compatible spices to exclude those already in selected aromatic groups
            $compatibleSpices = array_filter($compatibleSpices, function($spice) use ($selectedAromaticGroups) {
                return !in_array($spice['groupName'], $selectedAromaticGroups);
            });
        }

        if ($this->selectedAromaticGroup) {
            usort($compatibleSpices, function ($a, $b) {
                $groupA = $a['groupName'] === $this->selectedAromaticGroup ? 0 : 1;
                $groupB = $b['groupName'] === $this->selectedAromaticGroup ? 0 : 1;
                return $groupA <=> $groupB;
            });
        }

        return [
            'selectedSpices' => $groupByAromaticGroup ?? [],
            'compatibleSpices' => $compatibleSpices,
        ];
    }

    #[LiveAction]
    public function selectAromaticGroup(string $groupName)
    {
        $this->selectedAromaticGroup = $groupName;
    }

    #[LiveAction]
    public function addGroup()
    {
        $this->spices['selectedSpices'][] = [];
    }

    public function canAddMoreGroups(): bool
    {
        return !empty($this->getResults()['compatibleSpices']);
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
