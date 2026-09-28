<?php

declare(strict_types=1);

namespace App\Tests\Service\Education;

use App\Service\Education\AcademyManager;
use App\Service\Education\LocalizedSpiceNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocalizedSpiceNamesTest extends TestCase
{
    /**
     * @param array<int, array{canonical: string, localized: string, groupName: ?string}> $nameMap
     */
    #[DataProvider('provideMatches')]
    public function testMatches(string $given, string $expected, int $expectedId, array $nameMap, bool $result): void
    {
        self::assertSame($result, LocalizedSpiceNames::matches($given, $expected, $expectedId, $nameMap));
    }

    /**
     * @return iterable<string, array{string, string, int, array<int, array{canonical: string, localized: string, groupName: ?string}>, bool}>
     */
    public static function provideMatches(): iterable
    {
        $map = [
            42 => [
                'canonical' => 'Poivre',
                'localized' => 'Pepper',
                'groupName' => 'Terpenes',
            ],
            7 => [
                'canonical' => 'Cannelle',
                'localized' => 'Cinnamon',
                'groupName' => 'Terpenes',
            ],
        ];

        $frenchMap = [
            42 => [
                'canonical' => 'Poivre',
                'localized' => 'Poivre',
                'groupName' => 'Terpènes',
            ],
        ];

        yield 'saisie dans la locale courante' => ['Pepper', 'Poivre', 42, $map, true];
        yield 'saisie en français canonique' => ['Poivre', 'Poivre', 42, $map, true];
        yield 'autre épice dans la locale courante' => ['Cinnamon', 'Poivre', 42, $map, false];
        yield 'autre épice en français' => ['Cannelle', 'Poivre', 42, $map, false];
        yield 'saisie libre inconnue' => ['Wasabi', 'Poivre', 42, $map, false];
        yield 'casse différente refusée' => ['pepper', 'Poivre', 42, $map, false];
        yield 'accent manquant refusé' => ['Poivre', 'Poivré', 3, $frenchMap, false];
        yield 'accent présent accepté en canonique' => ['Poivré', 'Poivré', 3, $frenchMap, true];
        yield 'chaîne vide refusée' => ['', 'Poivre', 42, $map, false];
        yield 'chaîne vide attendue vide acceptée' => ['', '', 42, $map, true];
        yield 'locale fr les deux candidats identiques' => ['Poivre', 'Poivre', 42, $frenchMap, true];
        yield 'locale fr autre épice refusée' => ['Cannelle', 'Poivre', 42, $frenchMap, false];
        yield 'identifiant absent de la carte' => ['Pepper', 'Poivre', 0, $map, false];
        yield 'identifiant absent mais canonique juste' => ['Poivre', 'Poivre', 0, $map, true];
        yield 'identifiant inconnu de la table' => ['Pepper', 'Poivre', 999, $map, false];
    }

    public function testBuildKeepsCanonicalAndGroupWhenNothingIsLocalized(): void
    {
        $academyManager = $this->createMock(AcademyManager::class);
        $academyManager->expects(self::once())
            ->method('localizeSpiceSummaries')
            ->with([
                [
                    'id' => 42,
                    'name' => 'Poivre',
                    'file' => null,
                    'color' => null,
                    'groupName' => 'Terpènes',
                ],
                [
                    'id' => 7,
                    'name' => 'Cannelle',
                    'file' => null,
                    'color' => null,
                    'groupName' => null,
                ],
            ])
            ->willReturnArgument(0);

        $map = LocalizedSpiceNames::build(self::cards(), $academyManager);

        self::assertSame([
            42 => [
                'canonical' => 'Poivre',
                'localized' => 'Poivre',
                'groupName' => 'Terpènes',
            ],
            7 => [
                'canonical' => 'Cannelle',
                'localized' => 'Cannelle',
                'groupName' => null,
            ],
        ], $map);
    }

    public function testBuildAppliesLocalizedNamesAndGroupsInASingleBatch(): void
    {
        $academyManager = $this->createMock(AcademyManager::class);
        $academyManager->expects(self::once())
            ->method('localizeSpiceSummaries')
            ->willReturn([
                [
                    'id' => 42,
                    'name' => 'Pepper',
                    'file' => null,
                    'color' => null,
                    'groupName' => 'Terpenes',
                ],
                [
                    'id' => 7,
                    'name' => 'Cinnamon',
                    'file' => null,
                    'color' => null,
                    'groupName' => 'Terpenes',
                ],
            ]);

        $map = LocalizedSpiceNames::build(self::cards(), $academyManager);

        self::assertSame([
            42 => [
                'canonical' => 'Poivre',
                'localized' => 'Pepper',
                'groupName' => 'Terpenes',
            ],
            7 => [
                'canonical' => 'Cannelle',
                'localized' => 'Cinnamon',
                'groupName' => 'Terpenes',
            ],
        ], $map);
    }

    public function testBuildIgnoresLocalizedSummariesOutsideThePool(): void
    {
        $academyManager = $this->createMock(AcademyManager::class);
        $academyManager->expects(self::once())
            ->method('localizeSpiceSummaries')
            ->willReturn([
                [
                    'id' => 999,
                    'name' => 'Wasabi',
                    'file' => null,
                    'color' => null,
                    'groupName' => 'Soufrés',
                ],
            ]);

        $map = LocalizedSpiceNames::build(self::cards(), $academyManager);

        self::assertArrayNotHasKey(999, $map);
        self::assertSame('Poivre', $map[42]['localized']);
    }

    public function testBuildOnAnEmptyPoolStillDelegatesOnce(): void
    {
        $academyManager = $this->createMock(AcademyManager::class);
        $academyManager->expects(self::once())
            ->method('localizeSpiceSummaries')
            ->with([])
            ->willReturn([]);

        self::assertSame([], LocalizedSpiceNames::build([], $academyManager));
    }

    /**
     * @param array<string, string> $labelIndex
     */
    #[DataProvider('provideLabels')]
    public function testLabel(string $canonical, array $labelIndex, string $result): void
    {
        self::assertSame($result, LocalizedSpiceNames::label($canonical, $labelIndex));
    }

    /**
     * @return iterable<string, array{string, array<string, string>, string}>
     */
    public static function provideLabels(): iterable
    {
        $index = [
            'Poivre' => 'Pepper',
            'Cannelle' => 'Cinnamon',
        ];

        $frenchIndex = [
            'Poivre' => 'Poivre',
        ];

        yield 'canonique traduit' => ['Poivre', $index, 'Pepper'];
        yield 'autre canonique traduit' => ['Cannelle', $index, 'Cinnamon'];
        yield 'canonique absent rendu tel quel' => ['Wasabi', $index, 'Wasabi'];
        yield 'casse différente non traduite' => ['poivre', $index, 'poivre'];
        yield 'index vide rend le canonique' => ['Poivre', [], 'Poivre'];
        yield 'chaîne vide rendue telle quelle' => ['', $index, ''];
        yield 'locale fr rend le canonique' => ['Poivre', $frenchIndex, 'Poivre'];
        yield 'nom déjà localisé non retraduit' => ['Pepper', $index, 'Pepper'];
    }

    public function testLabelIndexProjectsLocalizedNamesByCanonicalName(): void
    {
        self::assertSame([
            'Poivre' => 'Pepper',
            'Cannelle' => 'Cinnamon',
        ], LocalizedSpiceNames::labelIndex([
            42 => [
                'canonical' => 'Poivre',
                'localized' => 'Pepper',
                'groupName' => 'Terpenes',
            ],
            7 => [
                'canonical' => 'Cannelle',
                'localized' => 'Cinnamon',
                'groupName' => null,
            ],
        ]));
    }

    public function testLabelIndexOnAnEmptyNameMapIsEmpty(): void
    {
        self::assertSame([], LocalizedSpiceNames::labelIndex([]));
    }

    public function testLabelIndexKeepsNoStateBetweenCalls(): void
    {
        $first = LocalizedSpiceNames::labelIndex([
            42 => [
                'canonical' => 'Poivre',
                'localized' => 'Pepper',
                'groupName' => null,
            ],
        ]);

        $second = LocalizedSpiceNames::labelIndex([
            7 => [
                'canonical' => 'Cannelle',
                'localized' => 'Canela',
                'groupName' => null,
            ],
        ]);

        self::assertSame([
            'Poivre' => 'Pepper',
        ], $first);
        self::assertSame([
            'Cannelle' => 'Canela',
        ], $second);
        self::assertSame('Poivre', LocalizedSpiceNames::label('Poivre', $second));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function cards(): array
    {
        return [
            42 => [
                'id' => 42,
                'name' => 'Poivre',
                'aromaticGroup' => [
                    'name' => 'Terpènes',
                    'color' => '#C00',
                ],
            ],
            7 => [
                'id' => 7,
                'name' => 'Cannelle',
            ],
        ];
    }
}
