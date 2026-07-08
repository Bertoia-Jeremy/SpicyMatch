<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Repository\SpicesRepository;
use App\Twig\Components\Search;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase
{
    #[DataProvider('provideShortQueries')]
    public function testShortQueryReturnsNoResultsWithoutSearching(string $query): void
    {
        $repository = $this->createMock(SpicesRepository::class);
        $repository->expects(self::never())->method('search');

        $component = new Search($repository);
        $component->query = $query;

        self::assertSame([], $component->getResults());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideShortQueries(): iterable
    {
        yield 'empty query' => [''];
        yield 'single character' => ['a'];
    }

    public function testQueryOfTwoCharactersOrMoreDelegatesToRepository(): void
    {
        $expected = [[
            'id' => 1,
            'name' => 'Cannelle',
            'type' => 'spice',
        ]];
        $repository = $this->createMock(SpicesRepository::class);
        $repository->expects(self::once())
            ->method('search')
            ->with('cann')
            ->willReturn($expected);

        $component = new Search($repository);
        $component->query = 'cann';

        self::assertSame($expected, $component->getResults());
    }
}
