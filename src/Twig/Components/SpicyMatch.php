<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\Repository\AromaticGroupsRepository;
use App\Repository\SpiceActiveCompoundRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyTypeRepository;
use App\Service\Match\CompatibleSpiceFinder;
use App\Service\Match\MatchConfidenceAssessorInterface;
use App\Service\SpicyMatchService;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class SpicyMatch extends AbstractController
{
    use DefaultActionTrait;

    /**
     * Presets prédéfinis exposés via setCookingPreset() — un seul clic suffit pour
     * basculer entre les trois grands modes culinaires sans toucher aux sliders.
     */
    private const array PRESETS = [
        'dry' => [
            'matrix' => 'air',
            'fat' => 0.0,
            'time' => 0,
            'temp' => 20,
        ],
        'broth' => [
            'matrix' => 'water',
            'fat' => 0.0,
            'time' => 20,
            'temp' => 80,
        ],
        'saute' => [
            'matrix' => 'oil',
            'fat' => 1.0,
            'time' => 10,
            'temp' => 140,
        ],
    ];

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

    // ── Contexte culinaire ──────────────────────────────────────────────────────

    #[LiveProp(writable: true)]
    public string $matrix = 'air';

    #[LiveProp(writable: true)]
    public float $fatRatio = 0.0;

    #[LiveProp(writable: true)]
    public int $cookingTimeMin = 0;

    #[LiveProp(writable: true)]
    public int $temperatureCelsius = 20;

    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly CompatibleSpiceFinder $compatibleSpiceFinder,
        private readonly AromaticGroupsRepository $aromaticGroupsRepository,
        private readonly SpicyTypeRepository $spicyTypeRepository,
        private readonly SpicyMatchService $spicyMatchService,
        private readonly MatchConfidenceAssessorInterface $confidenceAssessor,
        private readonly SpiceActiveCompoundRepository $spiceActiveCompoundRepository,
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

            $selectedFlat = $this->spicesRepository->findSpicesForMatch(implode(',', $ids));
            foreach ($selectedFlat as $spice) {
                $selectedSpicesData[$spice['groupName']][] = $spice;
            }

            if ($this->mode === 'auto') {
                $scored = $this->compatibleSpiceFinder->findCompatible(
                    new MortarIds($ids),
                    100,
                    $this->buildCulinaryContext(),
                );

                $compatibleSpices = array_values(array_filter(
                    $scored,
                    fn (array $s) => ! in_array($s['id'], $ids, true),
                ));
            } else {
                $compatibleSpices = array_values(array_filter(
                    $compatibleSpices,
                    fn (array $s) => ! in_array($s['id'], $ids, true),
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
                fn (array $s) => ($s['agId'] ?? null) === $agId,
            ));
        }

        if ($this->filterStId !== '') {
            $stId = (int) $this->filterStId;
            $compatibleSpices = array_values(array_filter(
                $compatibleSpices,
                fn (array $s) => ($s['stId'] ?? null) === $stId,
            ));
        }

        if ($this->search !== '') {
            $needle = mb_strtolower($this->search);
            $compatibleSpices = array_values(array_filter(
                $compatibleSpices,
                fn (array $s) => str_starts_with(mb_strtolower($s['name']), $needle),
            ));
        }

        return [
            'selectedSpices' => $selectedSpicesData,
            'compatibleSpices' => $compatibleSpices,
        ];
    }

    /**
     * Construit un CulinaryContext valide depuis les LiveProps (sanitization défensive).
     *
     * Les LiveProps sont writable côté client : on coerce/clamp avant d'instancier
     * pour garantir qu'aucune valeur hors-bornes ne lève d'InvalidArgumentException.
     */
    public function buildCulinaryContext(): CulinaryContext
    {
        // Bornes source de vérité : constantes publiques de CulinaryContext.
        $matrix = OdtMatrix::tryFrom(strtolower(trim($this->matrix))) ?? OdtMatrix::AIR;
        $fat = max(CulinaryContext::FAT_RATIO_MIN, min(CulinaryContext::FAT_RATIO_MAX, $this->fatRatio));
        $water = max(0.0, 1.0 - $fat);
        $time = max(CulinaryContext::COOKING_TIME_MIN, min(CulinaryContext::COOKING_TIME_MAX, $this->cookingTimeMin));
        $temp = max(CulinaryContext::TEMPERATURE_MIN, min(CulinaryContext::TEMPERATURE_MAX, $this->temperatureCelsius));

        try {
            return new CulinaryContext($matrix, $fat, $water, $time, $temp);
        } catch (\InvalidArgumentException) {
            // Garde-fou — ne devrait pas se produire après clamp ci-dessus.
            return new CulinaryContext();
        }
    }

    /**
     * Null tant qu'aucune épice n'est sélectionnée.
     */
    public function getDataConfidence(): ?DataConfidence
    {
        $selected = $this->spices['selectedSpices'];
        if ($selected === []) {
            return null;
        }

        $ids = array_values(array_filter(array_map('intval', $selected), static fn (int $id) => $id > 0));
        if ($ids === []) {
            return null;
        }

        return $this->confidenceAssessor->assess(new MortarIds($ids), $this->buildCulinaryContext()->matrix);
    }

    /**
     * Vrai si l'utilisateur a quitté le contexte par défaut.
     * Délégué au VO — source de vérité unique partagée avec l'entité.
     */
    public function hasCustomCulinaryContext(): bool
    {
        return $this->buildCulinaryContext()
            ->isCustom();
    }

    /**
     * Libellé court du mode culinaire courant pour l'affichage UI.
     * Délégué au VO.
     */
    public function getCulinaryLabel(): string
    {
        return $this->buildCulinaryContext()
            ->getLabel();
    }

    /**
     * Matrices proposables = celles ayant des données OAV réelles (véracité par omission).
     * Une matrice sans données n'apparaît pas dans le sélecteur.
     *
     * @return list<string>
     */
    public function getAvailableMatrices(): array
    {
        $withData = $this->spiceActiveCompoundRepository->matricesWithData();

        return array_values(array_filter(
            ['air', 'water', 'oil'],
            static fn (string $m): bool => in_array($m, $withData, true),
        ));
    }

    /**
     * Vrai si le mortier sélectionné a des données OAV dans la matrice courante
     * → score quantitatif réel. Faux → repli présence (à libeller comme tel, pas un score OAV).
     */
    public function isOavScoringAvailable(): bool
    {
        $ids = array_values(array_filter(
            array_map('intval', $this->spices['selectedSpices']),
            static fn (int $id): bool => $id > 0,
        ));

        return $this->spiceActiveCompoundRepository->hasDataForSpices($ids, $this->buildCulinaryContext()->matrix);
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

    /**
     * Bascule rapide entre les 3 modes culinaires types.
     * Whitelist stricte → toute valeur inconnue est ignorée.
     */
    #[LiveAction]
    public function setCookingPreset(#[LiveArg] string $preset): void
    {
        $config = self::PRESETS[$preset] ?? null;
        if ($config === null) {
            return;
        }

        $this->matrix = $config['matrix'];
        $this->fatRatio = $config['fat'];
        $this->cookingTimeMin = $config['time'];
        $this->temperatureCelsius = $config['temp'];
    }

    /**
     * Restaure le contexte culinaire par défaut (mode "À sec").
     */
    #[LiveAction]
    public function resetCulinaryContext(): void
    {
        $this->matrix = 'air';
        $this->fatRatio = 0.0;
        $this->cookingTimeMin = 0;
        $this->temperatureCelsius = 20;
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
    public function nextStep(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $isManual = $this->mode === 'manual';

        $user = $this->getUser();
        \assert($user instanceof \App\Entity\Users || $user === null);

        $selectedIds = array_map('intval', $this->spices['selectedSpices']);
        $compatibleSpices = $isManual ? [] : $this->getResults()['compatibleSpices'];

        $spicyMatch = $this->spicyMatchService->createFromSelection(
            $user,
            $selectedIds,
            $isManual,
            $compatibleSpices,
            $this->buildCulinaryContext(),
        );

        return $this->redirectToRoute('view_spicy_match', [
            'id' => $spicyMatch->getId(),
        ]);
    }
}
