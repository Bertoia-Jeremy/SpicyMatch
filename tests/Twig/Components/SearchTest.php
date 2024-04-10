<?php

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class SearchTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    public function testCanRenderAndInteract(): void
    {
        // TODO => Finaliser les tests pour valider la barre de recherche
        $testComponent = $this->createLiveComponent(
            name: 'Search', // can also use FQCN (Search::class)
            data: [
                'foo' => 'bar',
            ],
        );

        // render the component html
        $this->assertStringContainsString('Count: 0', $testComponent->render());

        // call live actions
        $testComponent
            ->call('increase')
            ->call('increase', [
                'amount' => 2,
            ]) // call a live action with arguments
        ;

        $this->assertStringContainsString('Count: 3', $testComponent->render());

        // set live props
        $testComponent
            ->set('count', 99)
        ;

        $this->assertStringContainsString('Count: 99', $testComponent->render());
    }
}
