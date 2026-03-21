<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Repository\AromaticGroupsRepository;
use App\Repository\SpicesRepository;
use App\Repository\SpicyTypeRepository;
use App\Service\CompatibilityScoreService;
use App\Twig\Components\SpicyMatch;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SpicyMatchTest extends TestCase
{
    private SpicesRepository&MockObject $spicesRepo;
    private CompatibilityScoreService&MockObject $compatibilityService;
    private AromaticGroupsRepository&MockObject $aromaticGroupsRepo;
    private SpicyTypeRepository&MockObject $spicyTypeRepo;

    /**
     * @var list<array<string, mixed>>
     */
    private array $allSpices;

    protected function setUp(): void
    {
        $this->spicesRepo = $this->createMock(SpicesRepository::class);
        $this->compatibilityService = $this->createMock(CompatibilityScoreService::class);
        $this->aromaticGroupsRepo = $this->createMock(AromaticGroupsRepository::class);
        $this->spicyTypeRepo = $this->createMock(SpicyTypeRepository::class);

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
            $this->compatibilityService,
            $this->aromaticGroupsRepo,
            $this->spicyTypeRepo,
        );
    }

    public function testModeDefaultsToAuto(): void
    {
        $component = $this->makeComponent();

        self::assertSame('auto', $component->mode);
    }

    public function testGetResultsWithNoSelectionReturnsAllSpices(): void
    {
        $component = $this->makeComponent();
        $results = $component->getResults();

        self::assertEmpty($results['selectedSpices']);
        self::assertCount(5, $results['compatibleSpices']);
    }

    public function testGetResultsInAutoModeCallsCompatibilityService(): void
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

        $this->spicesRepo->expects(self::once())
            ->method('findBy')
            ->willReturn([]);

        $this->compatibilityService->expects(self::once())
            ->method('findCompatible')
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

    public function testGetResultsInManualModeDoesNotCallCompatibilityService(): void
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

        $this->compatibilityService->expects(self::never())
            ->method('findCompatible');

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->spices = [
            'selectedSpices' => ['1'],
            'compatibleSpices' => $this->allSpices,
        ];

        $component->getResults();
    }

    public function testGetResultsInManualModeExcludesSelectedAndSameGroup(): void
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

        // Excludes id=1 (selected) and id=4 (same group "Chaud")
        $ids = array_column($results['compatibleSpices'], 'id');
        self::assertNotContains(1, $ids);
        self::assertNotContains(4, $ids);
        self::assertCount(3, $results['compatibleSpices']);
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
                ['id' => 1, 'name' => 'Cannelle', 'groupName' => 'Chaud', 'color' => '#C00'],
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

    public function testManualModeWithMultipleSelectionsExcludesSameGroups(): void
    {
        // Sélection de Cannelle (Chaud) et Cumin (Terreux)
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                ['id' => 1, 'name' => 'Cannelle', 'groupName' => 'Chaud', 'color' => '#C00'],
                ['id' => 2, 'name' => 'Cumin', 'groupName' => 'Terreux', 'color' => '#A52'],
            ]);

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->spices = [
            'selectedSpices' => ['1', '2'],
            'compatibleSpices' => $this->allSpices,
        ];

        $results = $component->getResults();
        $ids = array_column($results['compatibleSpices'], 'id');

        // Exclut Cannelle(1), Cumin(2), Gingembre(4 = même groupe Chaud)
        self::assertNotContains(1, $ids);
        self::assertNotContains(2, $ids);
        self::assertNotContains(4, $ids);
        // Reste Poivre(3) et Coriandre(5)
        self::assertContains(3, $ids);
        self::assertContains(5, $ids);
    }

    public function testManualModeWithAllSpicesSelectedReturnsEmpty(): void
    {
        // Sélection depuis chaque groupe → plus rien en compatible
        $this->spicesRepo->method('findSpicesForMatch')
            ->willReturn([
                ['id' => 1, 'name' => 'Cannelle', 'groupName' => 'Chaud', 'color' => '#C00'],
                ['id' => 2, 'name' => 'Cumin', 'groupName' => 'Terreux', 'color' => '#A52'],
                ['id' => 3, 'name' => 'Poivre', 'groupName' => 'Piquant', 'color' => '#333'],
                ['id' => 5, 'name' => 'Coriandre', 'groupName' => 'Herbacé', 'color' => '#0A0'],
            ]);

        $component = $this->makeComponent();
        $component->mode = 'manual';
        $component->spices = [
            'selectedSpices' => ['1', '2', '3', '5'],
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
                ['id' => 1, 'name' => 'Cannelle', 'groupName' => 'Chaud', 'color' => '#C00'],
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
                ['id' => 1, 'name' => 'Cannelle', 'groupName' => 'Chaud', 'color' => '#C00'],
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
                ['id' => 1, 'name' => 'Cannelle', 'groupName' => 'Chaud', 'color' => '#C00'],
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
                ['id' => 1, 'name' => 'Cannelle', 'groupName' => 'Chaud', 'color' => '#C00'],
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
}
