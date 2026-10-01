<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Entity\SpicyType;
use App\Entity\Users;
use App\Enum\DataConfidence;
use App\Enum\OdtMatrix;
use App\Enum\ScoringMode;
use App\Exception\Match\InvalidMortarException;
use App\Repository\AromaticGroupsRepository;
use App\Repository\SpiceActiveCompoundRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyTypeRepository;
use App\Service\Guest\GuestHistoryRegistry;
use App\Service\Match\CompatibleSpiceFinder;
use App\Service\Match\FlavorGraphHybridizer;
use App\Service\Match\FlavorGraphHybridizerInterface;
use App\Service\Match\MatchConfidenceAssessorInterface;
use App\Service\SpicyMatchService;
use App\Service\Text\SearchNormalizer;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class SpicyMatch extends AbstractController
{
    use DefaultActionTrait;

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

    #[LiveProp(writable: true)]
    public string $matrix = 'air';

    #[LiveProp(writable: true)]
    public float $fatRatio = 0.0;

    #[LiveProp(writable: true)]
    public int $cookingTimeMin = 0;

    #[LiveProp(writable: true)]
    public int $temperatureCelsius = 20;

    /**
     * @var list<int>|null
     */
    private ?array $excludedSpiceIdsCache = null;

    private ?int $resolvedAgId = null;

    private ?string $resolvedAgSlug = null;

    private ?int $resolvedStId = null;

    private ?string $resolvedStSlug = null;

    private ?bool $oavScoringAvailableCache = null;

    private ?string $oavScoringAvailableKey = null;

    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly CompatibleSpiceFinder $compatibleSpiceFinder,
        private readonly AromaticGroupsRepository $aromaticGroupsRepository,
        private readonly SpicyTypeRepository $spicyTypeRepository,
        private readonly SpicyMatchService $spicyMatchService,
        private readonly MatchConfidenceAssessorInterface $confidenceAssessor,
        private readonly SpiceActiveCompoundRepository $spiceActiveCompoundRepository,
        private readonly RequestStack $requestStack,
        private readonly FlavorGraphHybridizerInterface $hybridizer,
        private readonly SearchNormalizer $searchNormalizer,
        private readonly GuestHistoryRegistry $guestHistoryRegistry,
    ) {
        $this->spices = [
            'selectedSpices' => [],
            'compatibleSpices' => $spicesRepository->findAllSpices($requestStack->getCurrentRequest()?->getLocale()),
        ];
    }

    public function mount(): void
    {
        $user = $this->getUser();
        if (! $user instanceof Users) {
            return;
        }

        $preferred = $user->getDefaultMatrix()
            ->value;
        if (in_array($preferred, $this->getAvailableMatrices(), true)) {
            $this->matrix = $preferred;
        }
    }

    /**
     * @return list<int>
     */
    private function excludedSpiceIds(): array
    {
        if ($this->excludedSpiceIdsCache !== null) {
            return $this->excludedSpiceIdsCache;
        }

        $user = $this->getUser();
        if (! $user instanceof Users) {
            return $this->excludedSpiceIdsCache = [];
        }

        $ids = $user->getExcludedSpices()
            ->map(static fn (Spices $s): ?int => $s->getId())
            ->getValues();

        return $this->excludedSpiceIdsCache = array_values(array_filter(
            $ids,
            static fn (?int $id): bool => $id !== null,
        ));
    }

    public function getExcludedSpiceCount(): int
    {
        return count($this->excludedSpiceIds());
    }

    private function resolveAromaticGroupId(string $slug): ?int
    {
        if ($this->resolvedAgSlug !== $slug) {
            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
            $this->resolvedAgId = $this->aromaticGroupsRepository->findOneByLocalizedSlug($slug, $locale)?->getId();
            $this->resolvedAgSlug = $slug;
        }

        return $this->resolvedAgId;
    }

    private function resolveSpicyTypeId(string $slug): ?int
    {
        if ($this->resolvedStSlug !== $slug) {
            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
            $this->resolvedStId = $this->spicyTypeRepository->findOneByLocalizedSlug($slug, $locale)?->getId();
            $this->resolvedStSlug = $slug;
        }

        return $this->resolvedStId;
    }

    public function getActiveAromaticGroupId(): ?int
    {
        return $this->filterAgId !== '' ? $this->resolveAromaticGroupId($this->filterAgId) : null;
    }

    public function getActiveSpicyTypeId(): ?int
    {
        return $this->filterStId !== '' ? $this->resolveSpicyTypeId($this->filterStId) : null;
    }

    /**
     * @return list<AromaticGroups>
     */
    public function getAromaticGroups(): array
    {
        return $this->aromaticGroupsRepository->findAll();
    }

    /**
     * @return list<SpicyType>
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
            $ids = array_map(intval(...), $this->spices['selectedSpices']);

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
                    fn (array $s): bool => ! in_array($s['id'], $ids, true),
                ));
            } else {
                $compatibleSpices = array_values(array_filter(
                    $compatibleSpices,
                    fn (array $s): bool => ! in_array($s['id'], $ids, true),
                ));
            }
        }

        $excluded = $this->excludedSpiceIds();
        if ($excluded !== []) {
            $compatibleSpices = array_values(array_filter(
                $compatibleSpices,
                static fn (array $s): bool => ! in_array($s['id'], $excluded, true),
            ));
        }

        if ($this->selectedAromaticGroup !== null) {
            usort($compatibleSpices, function (array $a, array $b): int {
                $groupA = $a['groupName'] === $this->selectedAromaticGroup ? 0 : 1;
                $groupB = $b['groupName'] === $this->selectedAromaticGroup ? 0 : 1;

                return $groupA <=> $groupB;
            });
        }

        if ($this->filterAgId !== '') {
            $agId = $this->resolveAromaticGroupId($this->filterAgId);
            if ($agId !== null) {
                $compatibleSpices = array_values(array_filter(
                    $compatibleSpices,
                    fn (array $s): bool => ($s['agId'] ?? null) === $agId,
                ));
            }
        }

        if ($this->filterStId !== '') {
            $stId = $this->resolveSpicyTypeId($this->filterStId);
            if ($stId !== null) {
                $compatibleSpices = array_values(array_filter(
                    $compatibleSpices,
                    fn (array $s): bool => ($s['stId'] ?? null) === $stId,
                ));
            }
        }

        if (trim($this->search) !== '') {
            $compatibleSpices = array_values(array_filter(
                $compatibleSpices,
                fn (array $s): bool => $this->searchNormalizer->matches((string) $s['name'], $this->search),
            ));
        }

        return [
            'selectedSpices' => $selectedSpicesData,
            'compatibleSpices' => $compatibleSpices,
        ];
    }

    public function buildCulinaryContext(): CulinaryContext
    {
        $matrix = OdtMatrix::tryFrom(strtolower(trim($this->matrix))) ?? OdtMatrix::AIR;
        $fat = max(CulinaryContext::FAT_RATIO_MIN, min(CulinaryContext::FAT_RATIO_MAX, $this->fatRatio));
        $water = max(0.0, 1.0 - $fat);
        $time = max(CulinaryContext::COOKING_TIME_MIN, min(CulinaryContext::COOKING_TIME_MAX, $this->cookingTimeMin));
        $temp = max(CulinaryContext::TEMPERATURE_MIN, min(CulinaryContext::TEMPERATURE_MAX, $this->temperatureCelsius));

        try {
            return new CulinaryContext($matrix, $fat, $water, $time, $temp);
        } catch (\InvalidArgumentException) {
            return new CulinaryContext();
        }
    }

    public function getDataConfidence(): ?DataConfidence
    {
        $selected = $this->spices['selectedSpices'];
        if ($selected === []) {
            return null;
        }

        $ids = array_values(array_filter(array_map(intval(...), $selected), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return null;
        }

        return $this->confidenceAssessor->assess(new MortarIds($ids), $this->buildCulinaryContext()->matrix);
    }

    public function hasCustomCulinaryContext(): bool
    {
        return $this->buildCulinaryContext()
            ->isCustom();
    }

    public function getCulinaryLabel(): string
    {
        return $this->buildCulinaryContext()
            ->getLabel();
    }

    /**
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

    public function isOavScoringAvailable(): bool
    {
        $ids = array_values(array_filter(
            array_map(intval(...), $this->spices['selectedSpices']),
            static fn (int $id): bool => $id > 0,
        ));

        $matrix = $this->buildCulinaryContext()
            ->matrix;
        $key = $matrix->value . '|' . implode(',', $ids);

        if ($this->oavScoringAvailableKey === $key && $this->oavScoringAvailableCache !== null) {
            return $this->oavScoringAvailableCache;
        }

        $this->oavScoringAvailableKey = $key;

        return $this->oavScoringAvailableCache = $this->spiceActiveCompoundRepository->hasDataForSpices($ids, $matrix);
    }

    public function getScoringMode(): ScoringMode
    {
        return ScoringMode::resolve($this->isOavScoringAvailable(), $this->hybridizer->isActive());
    }

    public function getDegradedScoreMax(): int
    {
        return (int) round(100 * FlavorGraphHybridizer::DEGRADED_SCORE_SCALE);
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
        $this->spices['selectedSpices'] = [];
    }

    public function canAddMoreGroups(): bool
    {
        return ! empty($this->getResults()['compatibleSpices']);
    }

    #[LiveAction]
    public function nextStep(): RedirectResponse
    {
        $user = $this->getUser();
        $user = $user instanceof Users ? $user : null;
        $isManual = $this->mode === 'manual';

        $selectedIds = array_map(intval(...), $this->spices['selectedSpices']);
        $compatibleSpices = $isManual ? [] : $this->getResults()['compatibleSpices'];

        try {
            $history = $this->spicyMatchService->start(
                $user,
                $selectedIds,
                $isManual,
                $compatibleSpices,
                $this->buildCulinaryContext(),
            );
        } catch (InvalidMortarException) {
            return $this->redirectToRoute('index_spicy_match');
        }

        if ($user === null) {
            $this->guestHistoryRegistry->remember((int) $history->getId());
        }

        return $this->redirectToRoute('finalize_spicy_match_history', [
            'id' => $history->getId(),
        ]);
    }
}
