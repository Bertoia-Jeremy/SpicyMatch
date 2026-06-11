<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Enum\OdtMatrix;
use App\Repository\AromaticGroupsRepository;
use App\Repository\SpiceActiveCompoundRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyTypeRepository;
use App\Service\Match\CompatibleSpiceFinder;
use App\Service\Match\MatchConfidenceAssessorInterface;
use App\Service\SpicyMatchService;
use App\Twig\Components\SpicyMatch;
use App\ValueObject\Match\CulinaryContext;
use App\ValueObject\Match\MortarIds;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SpicyMatchTest extends TestCase
{
    private SpicesRepository&MockObject $spicesRepo;
    private CompatibleSpiceFinder&MockObject $compatibleSpiceFinder;
    private AromaticGroupsRepository&MockObject $aromaticGroupsRepo;
    private SpicyTypeRepository&MockObject $spicyTypeRepo;
    private SpicyMatchService&MockObject $spicyMatchService;
    private MatchConfidenceAssessorInterface&MockObject $confidenceAssessor;
    private SpiceActiveCompoundRepository&MockObject $spiceActiveCompoundRepo;

    /**
     * @var list<array<string, mixed>>
     */
    private array $allSpices;

    protected function setUp(): void
    {
        $this->spicesRepo = $this->createMock(SpicesRepository::class);
        $this->compatibleSpiceFinder = $this->createMock(CompatibleSpiceFinder::class);
        $this->aromaticGroupsRepo = $this->createMock(AromaticGroupsRepository::class);
        $this->spicyTypeRepo = $this->createMock(SpicyTypeRepository::class);
        $this->spicyMatchService = $this->createMock(SpicyMatchService::class);
        $this->confidenceAssessor = $this->createMock(MatchConfidenceAssessorInterface::class);
        $this->spiceActiveCompoundRepo = $this->createMock(SpiceActiveCompoundRepository::class);

        $this->allSpices = [
            [
                'id' => 1,
                'name' => 'Cannelle',
                'groupName' => 'Chaud',
                'agId' => 1,
                'stId' => 1,
                'color' => '#C00',
            ],
            [
                'id' => 2,
                'name' => 'Cumin',
                'groupName' => 'Terreux',
                'agId' => 2,
                'stId' => 1,
                'color' => '#A52',
            ],
            [
                'id' => 3,
                'name' => 'Poivre',
                'groupName' => 'Piquant',
                'agId' => 3,
                'stId' => 1,
                'color' => '#333',
            ],
            [
                'id' => 4,
                'name' => 'Gingembre',
                'groupName' => 'Chaud',
                'agId' => 1,
                'stId' => 1,
                'color' => '#FA0',
            ],
            [
                'id' => 5,
                'name' => 'Coriandre',
                'groupName' => 'Herbacé',
                'agId' => 4,
                'stId' => 2,
                'color' => '#0A0',
            ],
        ];

        $this->spicesRepo->method('findAllSpices')
            ->willReturn($this->allSpices);
    }

    private function makeComponent(): SpicyMatch
    {
        return new SpicyMatch(
            $this->spicesRepo,
            $this->compatibleSpiceFinder,
            $this->aromaticGroupsRepo,
            $this->spicyTypeRepo,
            $this->spicyMatchService,
            $this->confidenceAssessor,
            $this->spiceActiveCompoundRepo,
        );
    }

    public function testModeDefaultsToAuto(): void
    {
        $component = $this->makeComponent();

        self::assertSame('auto', $component->mode);
    }

    // ── Confiance des données ────────────────────────────────────────────────

    public function testDataConfidenceNullWhenNoSelection(): void
    {
        $this->confidenceAssessor->expects(self::never())
            ->method('assess');

        $component = $this->makeComponent();

        self::assertNull($component->getDataConfidence());
    }

    public function testDataConfidenceAssessedWhenSelectionExists(): void
    {
        $this->confidenceAssessor->expects(self::once())
            ->method('assess')
            ->willReturn(\App\Enum\DataConfidence::PLACEHOLDER);

        $component = $this->makeComponent();
        $component->spices = [
            'selectedSpices' => ['1', '2'],
            'compatibleSpices' => $this->allSpices,
        ];

        self::assertSame(\App\Enum\DataConfidence::PLACEHOLDER, $component->getDataConfidence());
    }

    public function testGetResultsWithNoSelectionReturnsAllSpices(): void
    {
        $component = $this->makeComponent();
        $results = $component->getResults();

        self::assertEmpty($results['selectedSpices']);
        self::assertCount(5, $results['compatibleSpices']);
    }

    public function testGetResultsInAutoModeCallsCompatibleSpiceFinder(): void
    {
        $this->spicesRepo->expects(self::once())
            ->method('findSpicesForMatch')
            ->with('1')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
            ]);

        $scored = [
            [
                'id' => 2,
                'name' => 'Cumin',
                'groupName' => 'Terreux',
                'agId' => 2,
                'stId' => 1,
                'score' => 80,
            ],
            [
                'id' => 3,
                'name' => 'Poivre',
                'groupName' => 'Piquant',
                'agId' => 3,
                'stId' => 1,
                'score' => 60,
            ],
            [
                'id' => 5,
                'name' => 'Coriandre',
                'groupName' => 'Herbacé',
                'agId' => 4,
                'stId' => 2,
                'score' => 40,
            ],
        ];

        $this->compatibleSpiceFinder->expects(self::once())
            ->method('findCompatible')
            ->with(new MortarIds([1]), 100, new CulinaryContext())
            ->willReturn($scored);

        $component = $this->makeComponent();
        $component->mode = 'auto';
        $component->spices = [
            'selectedSpices' => ['1'],
            'compatibleSpices' => $this->allSpices,
        ];

        $results = $component->getResults();

        self::assertCount(3, $results['compatibleSpices']);
    }

    public function testGetResultsInManualModeDoesNotCallCompatibleSpiceFinder(): void
    {
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
            ]);

        $this->compatibleSpiceFinder->expects(self::never())
            ->method('findCompatible');

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->spices = [
            'selectedSpices' => ['1'],
            'compatibleSpices' => $this->allSpices,
        ];

        $component->getResults();
    }

    public function testGetResultsInManualModeExcludesOnlySelected(): void
    {
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
            ]);

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->spices = [
            'selectedSpices' => ['1'],
            'compatibleSpices' => $this->allSpices,
        ];

        $results = $component->getResults();

        // Excludes only id=1 (selected) — same-group spices remain (id=4 Gingembre)
        $ids = array_column($results['compatibleSpices'], 'id');
        self::assertNotContains(1, $ids);
        self::assertContains(4, $ids);
        self::assertCount(4, $results['compatibleSpices']);
    }

    public function testResetFiltersClearsAllFilters(): void
    {
        $component = $this->makeComponent();
        $component->filterAgId = '2';
        $component->filterStId = '1';
        $component->search = 'cumin';

        $component->resetFilters();

        self::assertSame('', $component->filterAgId);
        self::assertSame('', $component->filterStId);
        self::assertSame('', $component->search);
    }

    // ── Manual mode: getResults() ───────────────────────────────────────────

    public function testManualModeResultsHaveNoScoreKey(): void
    {
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
            ]);

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->spices = [
            'selectedSpices' => ['1'],
            'compatibleSpices' => $this->allSpices,
        ];

        $results = $component->getResults();

        foreach ($results['compatibleSpices'] as $spice) {
            self::assertArrayNotHasKey('score', $spice, 'Manual mode should not contain score key');
        }
    }

    public function testManualModeWithMultipleSelectionsExcludesOnlySelected(): void
    {
        // Sélection de Cannelle (Chaud) et Cumin (Terreux)
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
                [
                    'id' => 2,
                    'name' => 'Cumin',
                    'groupName' => 'Terreux',
                    'color' => '#A52',
                ],
            ]);

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->spices = [
            'selectedSpices' => ['1', '2'],
            'compatibleSpices' => $this->allSpices,
        ];

        $results = $component->getResults();
        $ids = array_column($results['compatibleSpices'], 'id');

        // Exclut uniquement Cannelle(1) et Cumin(2)
        self::assertNotContains(1, $ids);
        self::assertNotContains(2, $ids);
        // Reste Poivre(3), Gingembre(4 — même groupe que Cannelle mais autorisé) et Coriandre(5)
        self::assertContains(3, $ids);
        self::assertContains(4, $ids);
        self::assertContains(5, $ids);
    }

    public function testManualModeWithAllSpicesSelectedReturnsEmpty(): void
    {
        // Toutes les épices sélectionnées → plus rien en compatible
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
                [
                    'id' => 2,
                    'name' => 'Cumin',
                    'groupName' => 'Terreux',
                    'color' => '#A52',
                ],
                [
                    'id' => 3,
                    'name' => 'Poivre',
                    'groupName' => 'Piquant',
                    'color' => '#333',
                ],
                [
                    'id' => 4,
                    'name' => 'Gingembre',
                    'groupName' => 'Chaud',
                    'color' => '#FA0',
                ],
                [
                    'id' => 5,
                    'name' => 'Coriandre',
                    'groupName' => 'Herbacé',
                    'color' => '#0A0',
                ],
            ]);

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->spices = [
            'selectedSpices' => ['1', '2', '3', '4', '5'],
            'compatibleSpices' => $this->allSpices,
        ];

        $results = $component->getResults();
        self::assertEmpty($results['compatibleSpices']);
    }

    // ── Manual mode: filters still work ─────────────────────────────────────

    public function testManualModeRespectsSearchFilter(): void
    {
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
            ]);

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->search = 'poi';
        $component->spices = [
            'selectedSpices' => ['1'],
            'compatibleSpices' => $this->allSpices,
        ];

        $results = $component->getResults();
        self::assertCount(1, $results['compatibleSpices']);
        self::assertSame('Poivre', $results['compatibleSpices'][0]['name']);
    }

    public function testManualModeRespectsAromaticGroupFilter(): void
    {
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
            ]);

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->filterAgId = '4'; // Herbacé
        $component->spices = [
            'selectedSpices' => ['1'],
            'compatibleSpices' => $this->allSpices,
        ];

        $results = $component->getResults();
        self::assertCount(1, $results['compatibleSpices']);
        self::assertSame('Coriandre', $results['compatibleSpices'][0]['name']);
    }

    public function testManualModeRespectsSpicyTypeFilter(): void
    {
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
            ]);

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->filterStId = '2'; // stId=2 → Coriandre only
        $component->spices = [
            'selectedSpices' => ['1'],
            'compatibleSpices' => $this->allSpices,
        ];

        $results = $component->getResults();
        self::assertCount(1, $results['compatibleSpices']);
        self::assertSame('Coriandre', $results['compatibleSpices'][0]['name']);
    }

    // ── canAddMoreGroups in manual mode ─────────────────────────────────────

    public function testCanAddMoreGroupsInManualModeWithAvailableSpices(): void
    {
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
            ]);

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->spices = [
            'selectedSpices' => ['1'],
            'compatibleSpices' => $this->allSpices,
        ];

        self::assertTrue($component->canAddMoreGroups());
    }

    // ── clearSearch ─────────────────────────────────────────────────────────

    public function testClearSearchInManualMode(): void
    {
        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->search = 'test';

        $component->clearSearch();

        self::assertSame('', $component->search);
    }

    // ── Contexte culinaire ──────────────────────────────────────────────────

    public function testDefaultCulinaryContextIsNeutral(): void
    {
        $component = $this->makeComponent();

        self::assertSame('air', $component->matrix);
        self::assertSame(0.0, $component->fatRatio);
        self::assertSame(0, $component->cookingTimeMin);
        self::assertSame(20, $component->temperatureCelsius);
        self::assertFalse($component->hasCustomCulinaryContext());
    }

    public function testBuildCulinaryContextReturnsValidObject(): void
    {
        $component = $this->makeComponent();
        $component->matrix = 'water';
        $component->fatRatio = 0.5;
        $component->cookingTimeMin = 20;
        $component->temperatureCelsius = 80;

        $ctx = $component->buildCulinaryContext();

        self::assertSame(OdtMatrix::WATER, $ctx->matrix);
        self::assertSame(0.5, $ctx->fatRatio);
        self::assertSame(0.5, $ctx->waterRatio);
        self::assertSame(20, $ctx->cookingTimeMin);
        self::assertSame(80, $ctx->temperatureCelsius);
    }

    public function testBuildCulinaryContextClampsFatRatioAboveOne(): void
    {
        // Sécurité : le client peut envoyer fat=2.5 via LiveProp writable → clamp à 1
        $component = $this->makeComponent();
        $component->fatRatio = 2.5;

        $ctx = $component->buildCulinaryContext();

        self::assertSame(1.0, $ctx->fatRatio);
        self::assertSame(0.0, $ctx->waterRatio);
    }

    public function testBuildCulinaryContextClampsFatRatioBelowZero(): void
    {
        $component = $this->makeComponent();
        $component->fatRatio = -0.3;

        $ctx = $component->buildCulinaryContext();

        self::assertSame(0.0, $ctx->fatRatio);
        self::assertSame(1.0, $ctx->waterRatio);
    }

    public function testBuildCulinaryContextClampsCookingTime(): void
    {
        $component = $this->makeComponent();
        $component->cookingTimeMin = 99_999;

        $ctx = $component->buildCulinaryContext();

        self::assertSame(1440, $ctx->cookingTimeMin); // cap 24 h
    }

    public function testBuildCulinaryContextClampsTemperature(): void
    {
        $component = $this->makeComponent();
        $component->temperatureCelsius = 9_999;

        $ctx = $component->buildCulinaryContext();

        self::assertSame(500, $ctx->temperatureCelsius);
    }

    public function testBuildCulinaryContextFallsBackToAirOnUnknownMatrix(): void
    {
        // Le client peut envoyer matrix=steam via writable → fallback air
        $component = $this->makeComponent();
        $component->matrix = 'steam';

        $ctx = $component->buildCulinaryContext();

        self::assertSame(OdtMatrix::AIR, $ctx->matrix);
    }

    public function testSetCookingPresetDry(): void
    {
        $component = $this->makeComponent();
        $component->matrix = 'oil';
        $component->fatRatio = 1.0;
        $component->cookingTimeMin = 30;
        $component->temperatureCelsius = 150;

        $component->setCookingPreset('dry');

        self::assertSame('air', $component->matrix);
        self::assertSame(0.0, $component->fatRatio);
        self::assertSame(0, $component->cookingTimeMin);
        self::assertSame(20, $component->temperatureCelsius);
    }

    public function testSetCookingPresetBroth(): void
    {
        $component = $this->makeComponent();
        $component->setCookingPreset('broth');

        self::assertSame('water', $component->matrix);
        self::assertSame(0.0, $component->fatRatio);
        self::assertSame(20, $component->cookingTimeMin);
        self::assertSame(80, $component->temperatureCelsius);
    }

    public function testSetCookingPresetSaute(): void
    {
        $component = $this->makeComponent();
        $component->setCookingPreset('saute');

        self::assertSame('oil', $component->matrix);
        self::assertSame(1.0, $component->fatRatio);
        self::assertSame(10, $component->cookingTimeMin);
        self::assertSame(140, $component->temperatureCelsius);
    }

    public function testSetCookingPresetIgnoresUnknownPreset(): void
    {
        // Whitelist stricte — un preset inconnu ne change rien
        $component = $this->makeComponent();
        $component->matrix = 'water';
        $component->fatRatio = 0.3;

        $component->setCookingPreset('hack');

        self::assertSame('water', $component->matrix);
        self::assertSame(0.3, $component->fatRatio);
    }

    public function testResetCulinaryContextRestoresDefaults(): void
    {
        $component = $this->makeComponent();
        $component->matrix = 'oil';
        $component->fatRatio = 0.75;
        $component->cookingTimeMin = 45;
        $component->temperatureCelsius = 180;

        $component->resetCulinaryContext();

        self::assertSame('air', $component->matrix);
        self::assertSame(0.0, $component->fatRatio);
        self::assertSame(0, $component->cookingTimeMin);
        self::assertSame(20, $component->temperatureCelsius);
        self::assertFalse($component->hasCustomCulinaryContext());
    }

    public function testHasCustomCulinaryContextDetectsAnyDeviation(): void
    {
        $component = $this->makeComponent();
        self::assertFalse($component->hasCustomCulinaryContext());

        $component->matrix = 'water';
        self::assertTrue($component->hasCustomCulinaryContext());
        $component->matrix = 'air';

        $component->fatRatio = 0.1;
        self::assertTrue($component->hasCustomCulinaryContext());
        $component->fatRatio = 0.0;

        $component->cookingTimeMin = 5;
        self::assertTrue($component->hasCustomCulinaryContext());
        $component->cookingTimeMin = 0;

        $component->temperatureCelsius = 21;
        self::assertTrue($component->hasCustomCulinaryContext());
    }

    public function testGetResultsPropagatesCulinaryContextToFinder(): void
    {
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                [
                    'id' => 1,
                    'name' => 'Cannelle',
                    'groupName' => 'Chaud',
                    'color' => '#C00',
                ],
            ]);

        $expectedCtx = new CulinaryContext(OdtMatrix::WATER, fatRatio: 0.5, waterRatio: 0.5);
        $this->compatibleSpiceFinder->expects(self::once())
            ->method('findCompatible')
            ->with(new MortarIds([1]), 100, $expectedCtx)
            ->willReturn([]);

        $component = $this->makeComponent();
        $component->matrix = 'water';
        $component->fatRatio = 0.5;
        $component->spices = [
            'selectedSpices' => ['1'],
            'compatibleSpices' => $this->allSpices,
        ];

        $component->getResults();
    }

    public function testGetCulinaryLabelReturnsHumanReadableLabel(): void
    {
        $component = $this->makeComponent();

        self::assertSame('À sec', $component->getCulinaryLabel());

        $component->matrix = 'water';
        self::assertSame('Eau', $component->getCulinaryLabel());

        $component->matrix = 'oil';
        self::assertSame('Huile', $component->getCulinaryLabel());

        // Cuisson en bouillon
        $component->matrix = 'water';
        $component->fatRatio = 0.0;
        $component->cookingTimeMin = 20;
        self::assertSame('Bouillon', $component->getCulinaryLabel());

        // Sauté
        $component->matrix = 'oil';
        $component->fatRatio = 1.0;
        $component->cookingTimeMin = 10;
        self::assertSame('Sauté', $component->getCulinaryLabel());
    }
}
