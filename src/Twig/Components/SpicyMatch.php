<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Spices;
use App\Factory\SpicyMatchFactory;
use App\Repository\AromaticGroupsRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyTypeRepository;
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

    /**
     * @var array{selectedSpices: list<int|string>, compatibleSpices: list<array<string, mixed>>}
     */
    #[LiveProp(writable: true)]
    public array $spices;

    #[LiveProp(writable: true)]
    public ?string $selectedAromaticGroup = null;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public string $filterAgId = '';

    #[LiveProp(writable: true)]
    public string $filterStId = '';

    #[LiveProp(writable: true)]
    public string $mode = 'auto';

    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly CompatibilityScoreService $compatibilityScoreService,
        private readonly AromaticGroupsRepository $aromaticGroupsRepository,
        private readonly SpicyTypeRepository $spicyTypeRepository,
    ) {
        $this->spices = [
            'selectedSpices' => [],
            'compatibleSpices' => $spicesRepository->findAllSpices(),
        ];
    }

    /**
     * @return list<\App\Entity\AromaticGroups>
     */
    public function getAromaticGroups(): array
    {
        return $this->aromaticGroupsRepository->findAll();
    }

    /**
     * @return list<\App\Entity\SpicyType>
     */
    public function getSpicyTypes(): array
    {
        return $this->spicyTypeRepository->findAll();
    }

    /**
     * @return array{selectedSpices: array<string, list<array<string, mixed>>>, compatibleSpices: list<array<string, mixed>>}
     */
    public function getResults(): array
    {
        $selectedSpicesData = [];
        $compatibleSpices = $this->spices['compatibleSpices'];

        if (! empty($this->spices['selectedSpices'])) {
            $ids = array_map('intval', $this->spices['selectedSpices']);

            // Flat arrays for display (grouped by aromatic group)
            $selectedFlat = $this->spicesRepository->findSpicesForMatch(implode(',', $ids));
            foreach ($selectedFlat as $spice) {
                $selectedSpicesData[$spice['groupName']][] = $spice;
            }

            if ($this->mode === 'auto') {
                // Load entities for scoring
                $selectedEntities = $this->spicesRepository->findBy([
                    'id' => $ids,
                ]);
                $scored = $this->compatibilityScoreService->findCompatible($selectedEntities);

                // Filter out spices from the same aromatic groups as the selection
                $selectedGroupNames = array_keys($selectedSpicesData);
                $compatibleSpices = array_values(array_filter(
                    $scored,
                    fn (array $s) => ! in_array($s['groupName'], $selectedGroupNames, true)
                ));
            } else {
                // Manual mode: show all spices except already selected, no scoring
                $selectedGroupNames = array_keys($selectedSpicesData);
                $compatibleSpices = array_values(array_filter(
                    $compatibleSpices,
                    fn (array $s) => ! in_array($s['groupName'], $selectedGroupNames, true)
                        && ! in_array($s['id'], $ids, true)
                ));
            }
        }

        if ($this->selectedAromaticGroup !== null) {
            usort($compatibleSpices, function (array $a, array $b) {
                $groupA = $a['groupName'] === $this->selectedAromaticGroup ? 0 : 1;
                $groupB = $b['groupName'] === $this->selectedAromaticGroup ? 0 : 1;

                return $groupA <=> $groupB;
            });
        }

        if ($this->filterAgId !== '') {
            $agId = (int) $this->filterAgId;
            $compatibleSpices = array_values(array_filter(
                $compatibleSpices,
                fn (array $s) => ($s['agId'] ?? null) === $agId
            ));
        }

        if ($this->filterStId !== '') {
            $stId = (int) $this->filterStId;
            $compatibleSpices = array_values(array_filter(
                $compatibleSpices,
                fn (array $s) => ($s['stId'] ?? null) === $stId
            ));
        }

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $compatibleSpices = array_values(array_filter(
                $compatibleSpices,
                fn (array $s) => str_starts_with(mb_strtolower($s['name']), $needle)
            ));
        }

        return [
            'selectedSpices' => $selectedSpicesData,
            'compatibleSpices' => $compatibleSpices,
        ];
    }

    #[LiveAction]
    public function resetFilters(): void
    {
        $this->filterAgId = '';
        $this->filterStId = '';
        $this->search = '';
    }

    #[LiveAction]
    public function clearSearch(): void
    {
        $this->search = '';
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

    #[LiveAction]
    public function clearSelection(): void
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $this->spices['selectedSpices'] = [];
    }

    public function canAddMoreGroups(): bool
    {
        return ! empty($this->getResults()['compatibleSpices']);
    }

    #[LiveAction]
    public function nextStep(
        EntityManagerInterface $entityManager,
        SpicyMatchFactory $spicyMatchFactory,
    ): \Symfony\Component\HttpFoundation\RedirectResponse {
        $isManual = $this->mode === 'manual';

        $user = $this->getUser();
        \assert($user instanceof \App\Entity\Users || $user === null);

        $spicyMatch = $spicyMatchFactory->create();
        $spicyMatch->setUser($user);
        $spicyMatch->setIsManual($isManual);

        // Extraction des IDs depuis la structure SpicyMatch (tableau plat d'IDs)
        $selectedIds = array_map('intval', $this->spices['selectedSpices']);

        foreach ($selectedIds as $spiceId) {
            /** @var Spices|null $spice */
            $spice = $this->spicesRepository->find($spiceId);
            if ($spice) {
                $spicyMatch->addSpice($spice);
            }
        }

        // En mode auto, on sauvegarde les résultats scorés pour référence
        // En mode manuel, pas de score de compatibilité → on skip les results
        if (! $isManual) {
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
        }

        $entityManager->persist($spicyMatch);
        $entityManager->flush();

        return $this->redirectToRoute('view_spicy_match', [
            'id' => $spicyMatch->getId(),
        ]);
    }
}
