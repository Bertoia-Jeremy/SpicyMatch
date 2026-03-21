<?php

namespace App\Tests\Twig\Components;

use App\Repository\SpicesRepository;
use App\Twig\Components\Search;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class SearchTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    public function testSearchFunctionality(): void
    {
        $mockSpicesRepository = $this->createMock(SpicesRepository::class);

        // Test case 1: Empty query
        $mockSpicesRepository->expects($this->once())
            ->method('search')
            ->with('')
            ->willReturn([]);

        $component = $this->createLiveComponent(Search::class, [], [
            SpicesRepository::class => $mockSpicesRepository,
        ]);

        $component->set('query', '');
        $this->assertEmpty($component->get('results'));

        // Test case 2: Short query (less than 2 characters)
        $mockSpicesRepository->expects($this->once())
            ->method('search')
            ->with('a')
            ->willReturn([]);

        $component->set('query', 'a');
        $this->assertEmpty($component->get('results'));

        // Test case 3: Valid query with results
        $expectedResults = [[
            'id' => 1,
            'name' => 'Cinnamon',
        ]];
        $mockSpicesRepository->expects($this->once())
            ->method('search')
            ->with('cinn')
            ->willReturn($expectedResults);

        $component->set('query', 'cinn');
        $this->assertEquals($expectedResults, $component->get('results'));

        // Test case 4: Valid query with no results
        $mockSpicesRepository->expects($this->once())
            ->method('search')
            ->with('xyz')
            ->willReturn([]);

        $component->set('query', 'xyz');
        $this->assertEmpty($component->get('results'));
    }
}
