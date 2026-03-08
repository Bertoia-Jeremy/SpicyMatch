<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Spices;
use App\Factory\SpicyMatchFactory;
use App\Repository\SpicesRepository;
use App\Service\CompatibilityScoreService;
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
    public array $spices;

    #[LiveProp(writable: true)]
    public ?string $selectedAromaticGroup = null;

    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly CompatibilityScoreService $compatibilityScoreService,
    ) {
        $this->spices = [
            'selectedSpices'   => [],
            'compatibleSpices' => $spicesRepository->findAllSpices(),
        ];
    }

    public function getResults(): array
    {
        $selectedSpicesData = [];
        $compatibleSpices = $this->spices['compatibleSpices'];

        if (!empty($this->spices['selectedSpices'])) {
            $ids = array_map('intval', $this->spices['selectedSpices']);

            // Flat arrays for display (grouped by aromatic group)
            $selectedFlat = $this->spicesRepository->findSpicesForMatch(implode(',', $ids));
            foreach ($selectedFlat as $spice) {
                $selectedSpicesData[$spice['groupName']][] = $spice;
            }

            // Load entities for scoring
            $selectedEntities = $this->spicesRepository->findBy(['id' => $ids]);
            $scored = $this->compatibilityScoreService->findCompatible($selectedEntities);

            // Filter out spices from the same aromatic groups as the selection
            $selectedGroupNames = array_keys($selectedSpicesData);
            $compatibleSpices = array_values(array_filter(
                $scored,
                fn (array $s) => !in_array($s['groupName'], $selectedGroupNames, true)
            ));
        }

        if ($this->selectedAromaticGroup !== null) {
            usort($compatibleSpices, function (array $a, array $b) {
                $groupA = $a['groupName'] === $this->selectedAromaticGroup ? 0 : 1;
                $groupB = $b['groupName'] === $this->selectedAromaticGroup ? 0 : 1;
                return $groupA <=> $groupB;
            });
        }

        return [
            'selectedSpices'   => $selectedSpicesData,
            'compatibleSpices' => $compatibleSpices,
        ];
    }

    #[LiveAction]
    public function selectAromaticGroup(string $groupName): void
    {
        $this->selectedAromaticGroup = $groupName;
    }

    #[LiveAction]
    public function addGroup(): void
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
        SpicyMatchFactory $spicyMatchFactory,
    ): \Symfony\Component\HttpFoundation\RedirectResponse {
        $spicyMatch = $spicyMatchFactory->create();
        $spicyMatch->setUser($this->getUser());

        // Extraction des IDs depuis la structure SpicyMatch (tableau plat d'IDs)
        $selectedIds = array_map('intval', $this->spices['selectedSpices']);

        foreach ($selectedIds as $spiceId) {
            /** @var Spices|null $spice */
            $spice = $this->spicesRepository->find($spiceId);
            if ($spice) {
                $spicyMatch->addSpice($spice);
            }
        }

        // On recalcule les résultats pour les sauvegarder dans l'entité SpicyMatchResult
        // Cela permet de garder une trace des scores au moment du mélange
        $results = $this->getResults();
        foreach ($results['compatibleSpices'] as $compatibleData) {
            $spice = $this->spicesRepository->find($compatibleData['id']);
            if ($spice) {
                $result = new \App\Entity\SpicyMatchResult();
                $result->setSpice($spice);
                $result->setScore((int) $compatibleData['score']);
                $spicyMatch->addResult($result);
            }
        }

        $entityManager->persist($spicyMatch);
        $entityManager->flush();

        return $this->redirectToRoute('view_spicy_match', ['id' => $spicyMatch->getId()]);
    }
}
